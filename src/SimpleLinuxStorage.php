<?php

namespace PhotoCentralSimpleLinuxStorage;

use LinuxFileSystemHelper\FileHelper;
use LinuxFileSystemHelper\FolderHelper;
use LinuxImageHelper\Exception\LinuxImageHelperException;
use PhotoCentralSimpleLinuxStorage\Factory\LinuxFileFactory;
use PhotoCentralSimpleLinuxStorage\Factory\PhotoFactory;
use PhotoCentralSimpleLinuxStorage\Model\LinuxFile;
use PhotoCentralSimpleLinuxStorage\Service\PhotoRetrivalService;
use PhotoCentralStorage\Exception\PhotoCentralStorageException;
use PhotoCentralStorage\Factory\ExifDataFactory;
use PhotoCentralStorage\Model\ImageDimensions;
use PhotoCentralStorage\Model\PhotoFilter\PhotoCollectionIdFilter;
use PhotoCentralStorage\Model\PhotoFilter\PhotoFilter;
use PhotoCentralStorage\Model\PhotoFilter\CreatedTimestampRangeFilter;
use PhotoCentralStorage\Model\PhotoFilter\PhotoUuidFilter;
use PhotoCentralStorage\Model\PhotoSorting\BasicSorting;
use PhotoCentralStorage\Model\PhotoSorting\PhotoSorting;
use PhotoCentralStorage\Model\PhotoSorting\SortByAddedTimestamp;
use PhotoCentralStorage\Model\PhotoSorting\SortByCreatedTimestamp;
use PhotoCentralStorage\Photo;
use PhotoCentralStorage\PhotoCollection;
use PhotoCentralStorage\PhotoCentralStorage;

class SimpleLinuxStorage implements PhotoCentralStorage
{
    public const PHOTO_COLLECTION_UUID = '427e8cdc-2275-4b54-942c-3295b2e300e2';
    public const TRASH_FOLDER_NAME     = '.trash/';

    private string $photo_path;
    private PhotoCollection $photo_collection;
    /**
     * @var null|LinuxFile[]
     */
    private ?array $linux_file_map = null;
    /**
     * @var null|Photo[]
     */
    private ?array $photo_map = null;
    private string $image_cache_path;

    public function __construct(string $photo_path, string $image_cache_path)
    {
        $this->photo_path = $photo_path;
        $this->photo_collection = new PhotoCollection(self::PHOTO_COLLECTION_UUID, 'Photo folder',
            "Simple Linux Storage folder ($this->photo_path)");
        $this->image_cache_path = $image_cache_path;
    }

    public function searchPhotos(string $search_string): array
    {
        $search_result_list = [];
        $this->readPhotos();

        foreach ($this->linux_file_map as $linux_file) {
            str_replace($search_string, $search_string, $linux_file->getFilePath() . $linux_file->getFileName(),
                $count);
            if ($count > 0) {
                $search_result_list[] = $this->photo_map[$linux_file->getPhotoUuid()];
            }
        }

        return $search_result_list;
    }

    public function listPhotos(array $photo_filters = null, PhotoSorting $photo_sorting = null, int $limit = 5): array
    {
        $this->readPhotos();

        $photo_list = $this->photo_map;

        if ($photo_filters !== null) {
            $photo_list = $this->filterPhotoList($photo_filters);
        }

        if ($photo_sorting !== null) {
            $photo_list = $this->sortPhotoList($photo_sorting, $photo_list);
        }

        return array_slice($photo_list, 0, $limit, true);
    }

    /**
     * @param PhotoFilter[] $photo_filters
     *
     * @return Photo[]
     */
    private function filterPhotoList(array $photo_filters): array
    {
        $photo_list = [];
        foreach ($this->photo_map as $photo) {
            foreach ($photo_filters as $photo_filter) {
                $photo_uuid = $photo->getPhotoUuid();

                // TODO : This is not SOLID enough
                if ($photo_filter instanceof PhotoUuidFilter) {
                    if (in_array($photo_uuid, $photo_filter->getPhotoUuidList())) {
                        $photo_list[$photo_uuid] = $photo;
                    } else {
                        if (array_key_exists($photo_uuid, $photo_list)) {
                            unset($photo_list[$photo_uuid]);
                        }
                        break;
                    }
                }

                if ($photo_filter instanceof CreatedTimestampRangeFilter) {
                    if ($photo->getExifDateTime() >= $photo_filter->getStartTimestamp() && $photo->getExifDateTime() <= $photo_filter->getEndTimestamp()) {
                        $photo_list[$photo_uuid] = $photo;
                    } else {
                        if (array_key_exists($photo_uuid, $photo_list)) {
                            unset($photo_list[$photo_uuid]);
                        }
                        break;
                    }
                }

                if ($photo_filter instanceof PhotoCollectionIdFilter) {
                    if (in_array($photo->getPhotoCollectionUuid(), $photo_filter->getPhotoCollectionIdList())) {
                        $photo_list[$photo_uuid] = $photo;
                    } else {
                        if (array_key_exists($photo_uuid, $photo_list)) {
                            unset($photo_list[$photo_uuid]);
                        }
                        break;
                    }
                }
            }
        }
        return $photo_list;
    }

    /**
     * @param PhotoSorting $photo_sorting
     * @param Photo[]      $photo_list
     *
     * @return Photo[]
     */
    private function sortPhotoList(PhotoSorting $photo_sorting, array $photo_list): array
    {
        // TODO : This is not SOLID enough
        if ($photo_sorting instanceof SortByCreatedTimestamp) {
            if ($photo_sorting->getDirection() === BasicSorting::ASC) {
                uasort($photo_list, fn($a, $b) => ($a->getExifDateTime() ?? $a->getFallbackDateTime()) > ($b->getExifDateTime() ?? $b->getFallbackDateTime()));
            } else {
                uasort($photo_list, fn($a, $b) => ($a->getExifDateTime() ?? $a->getFallbackDateTime()) < ($b->getExifDateTime() ?? $b->getFallbackDateTime()));
            }
        } else if ($photo_sorting instanceof SortByAddedTimestamp) {
            if ($photo_sorting->getDirection() === BasicSorting::ASC) {
                uasort($photo_list, fn($a, $b) => ($a->getPhotoAddedDateTime()) > ($b->getPhotoAddedDateTime()));
            } else {
                uasort($photo_list, fn($a, $b) => ($a->getPhotoAddedDateTime()) < ($b->getPhotoAddedDateTime()));
            }
        }

        return $photo_list;
    }

    /**
     * @throws PhotoCentralStorageException
     */
    public function getPhoto(string $photo_uuid): Photo
    {
        $this->readPhotos();

        if (isset($this->photo_map[$photo_uuid]) === false) {
            throw new PhotoCentralStorageException("No photo could be found with the supplied uuid $photo_uuid");
        }

        return $this->photo_map[$photo_uuid];
    }

    public function softDeletePhoto(string $photo_uuid): bool
    {
        $this->readPhotos();

        if (isset($this->photo_map[$photo_uuid]) === false) {
            throw new PhotoCentralStorageException("No photo could be found with the supplied uuid $photo_uuid");
        }

        // Create trash folder
        FolderHelper::createFolder($this->photo_path . self::TRASH_FOLDER_NAME);

        // Get file to be deleted
        $linux_file_to_softe_delete = $this->linux_file_map[$photo_uuid];

        // Build trash destination folder name and create it
        $trash_folder_destination = $this->photo_path . self::TRASH_FOLDER_NAME . $linux_file_to_softe_delete->getFilePath();
        FolderHelper::createFolder($trash_folder_destination);

        // Softdelete file by moving it into trash folder
        FileHelper::moveFile($linux_file_to_softe_delete->getFullFileNameAndPath($this->photo_path),
            $trash_folder_destination . $linux_file_to_softe_delete->getFileName());

        // Remove folder after moving file to trash if folder is empty
        $folder_to_remove = $this->photo_path . $linux_file_to_softe_delete->getFilePath();
        $this->removeUnusedFoldersRecursively($folder_to_remove);

        return true;
    }

    public function undoSoftDeletePhoto(string $photo_uuid): bool
    {
        // Build a list of deleted files
        $deleted_linux_file_map = $this->buildDeletedLinuxFilesMap();

        // Get the file that should be un-deleted
        $linux_file_to_undelete = $deleted_linux_file_map[$photo_uuid];

        // build source path and file name
        $source_file_name_and_path = $linux_file_to_undelete->getFullFileNameAndPath($this->photo_path . self::TRASH_FOLDER_NAME);

        // re-build orgiginal path and file name
        $original_destination_file_name_and_folder = $linux_file_to_undelete->getFullFileNameAndPath($this->photo_path);

        // re-create folder that the deleted file was in
        FolderHelper::createFolder($this->photo_path . $linux_file_to_undelete->getFilePath());

        // Undo soft delete by moving file back to original folder
        FileHelper::moveFile($source_file_name_and_path, $original_destination_file_name_and_folder);

        // Remove folder inside trash (including trash) if empty
        $folder_to_remove = $this->photo_path . self::TRASH_FOLDER_NAME . $linux_file_to_undelete->getFilePath();
        $this->removeUnusedFoldersRecursively($folder_to_remove);

        return true;
    }

    public function listPhotoCollections(int $limit): array
    {
        return [$this->photo_collection];
    }

    private function removeUnusedFoldersRecursively(string $path): void
    {
        if (FolderHelper::isFolderEmpty($path)) {
            rmdir($path);
            $this->removeUnusedFoldersRecursively(dirname($path));
        }
    }

    /**
     * @return LinuxFile[]
     */
    private function buildDeletedLinuxFilesMap(): array
    {
        $deleted_linux_file_map = [];

        $deleted_file_list = FolderHelper::listFilesRecursiveFromFolder($this->photo_path . self::TRASH_FOLDER_NAME,
            '.jpg');

        foreach ($deleted_file_list as $deleted_file) {
            $deleted_linux_file = LinuxFileFactory::createLinuxFile($deleted_file,
                $this->photo_path . self::TRASH_FOLDER_NAME);
            $deleted_linux_file_map[$deleted_linux_file->getPhotoUuid()] = $deleted_linux_file;
        }

        return $deleted_linux_file_map;
    }

    private function readPhotos()
    {
        if ($this->linux_file_map === null && $this->photo_map === null) {
            // TODO: Simple Linux Storage should have a upper limit
            $jpg_file_list = FolderHelper::listFilesRecursiveFromFolder($this->photo_path, '.jpg',
                [trim(self::TRASH_FOLDER_NAME, '/')]);

            foreach ($jpg_file_list as $jpg_file) {
                $new_linux_file = LinuxFileFactory::createLinuxFile($jpg_file, $this->photo_path);
                $exif_data = ExifDataFactory::createExifData($new_linux_file->getFullFileNameAndPath($this->photo_path));
                $new_photo = PhotoFactory::createPhoto($new_linux_file, $exif_data);

                $this->linux_file_map[$new_linux_file->getPhotoUuid()] = $new_linux_file;
                $this->photo_map[$new_linux_file->getPhotoUuid()] = $new_photo;
            }
        }
    }

    /**
     * @throws PhotoCentralStorageException
     * @throws LinuxImageHelperException
     */
    public function getPhotoPath(string $photo_uuid, ImageDimensions $image_dimensions): string
    {
        $this->readPhotos();
        $photo_retrival_service = new PhotoRetrivalService($this->photo_path, $this->image_cache_path);

        return $photo_retrival_service->getPhotoPath($this->linux_file_map[$photo_uuid], $image_dimensions);
    }
}