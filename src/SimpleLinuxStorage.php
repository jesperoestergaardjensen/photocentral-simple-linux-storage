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
use PhotoCentralStorage\Photo;
use PhotoCentralStorage\PhotoCollection;
use PhotoCentralStorage\PhotoStorage;

class SimpleLinuxStorage implements PhotoStorage
{
    public const PHOTO_COLLECTION_ID = 1;
    public const TRASH_FOLDER_NAME   = '.trash/';

    private string $photo_path;
    private PhotoCollection $photo_collection;
    /**
     * @var null|LinuxFile[]
     */
    private ?array $linux_file_map = null;
    private ?array $photo_map = null;
    private string $image_cache_path;

    public function __construct(string $photo_path, string $image_cache_path)
    {
        $this->photo_path = $photo_path;
        $this->photo_collection = new PhotoCollection(self::PHOTO_COLLECTION_ID, 'Photo folder',
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

    // TODO : Tanker ved exit søndag aften 14-11-2021

    /**
     * order by på tid kan måske gøre allerede ved fil liste generering?
     *
     * filter måske på sigt : GPS info eller ej, kamera type, billeder over en vis størrelse
     *
     */

    public function listPhotos(
        int $start_unix_timestamp,
        int $end_unix_timestamp,
        $order_by,
        int $limit,
        array $photo_collection_filter_uuid_list = null
    ): array {

        $this->readPhotos();

        return $this->photo_map;
    }

    public function getPhoto(string $photo_uuid): Photo
    {
        $this->readPhotos();

        if (isset($this->photo_map[$photo_uuid]) === false) {
            throw new PhotoCentralStorageException("No photo could be found with the supplied uuid $photo_uuid");
        }

        return $this->photo_map[$photo_uuid];
    }

    public function getPhotos(array $photo_uuid_list): array
    {
        $this->readPhotos();

        $photo_list = [];

        foreach ($photo_uuid_list as $photo_uuid) {
            $photo_list[] = $this->getPhoto($photo_uuid);
        }

        return $photo_list;
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

    private function removeUnusedFoldersRecursively(string $path): void {
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
            $jpg_file_list = FolderHelper::listFilesRecursiveFromFolder($this->photo_path, '.jpg', [trim(self::TRASH_FOLDER_NAME, '/')]);

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