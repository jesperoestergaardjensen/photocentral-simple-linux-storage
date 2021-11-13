<?php

namespace PhotoCentralSimpleLinuxStorage;

use LinuxFileSystemHelper\FolderHelper;
use PhotoCentralSimpleLinuxStorage\Factory\LinuxFileFactory;
use PhotoCentralSimpleLinuxStorage\Factory\PhotoFactory;
use PhotoCentralSimpleLinuxStorage\Model\LinuxFile;
use PhotoCentralStorage\Exception\PhotoCentralStorageException;
use PhotoCentralStorage\Factory\ExifDataFactory;
use PhotoCentralStorage\Photo;
use PhotoCentralStorage\PhotoCollection;
use PhotoCentralStorage\PhotoStorage;

class SimpleLinuxStorage implements PhotoStorage
{
    public const PHOTO_COLLECTION_ID = 1;

    private string $photo_path;
    private string $status_file_path;
    private PhotoCollection $photo_collection;
    /**
     * @var null|LinuxFile[]
     */
    private ?array $linux_file_map = null;
    private ?array $photo_map = null;

    public function __construct(string $photo_path, string $status_file_path)
    {
        $this->photo_path = $photo_path;
        $this->status_file_path = $status_file_path;
        $this->photo_collection = new PhotoCollection(self::PHOTO_COLLECTION_ID, 'Photo folder',
            "Simple Linux Storage folder ($this->photo_path)");
    }

    public function searchPhotos(string $search_string): array
    {
        $search_result_list = [];
        $this->readPhotos();

        foreach ($this->linux_file_map as $linux_file) {
            str_replace($search_string, $search_string, $linux_file->getFilePath() . $linux_file->getFileName(), $count);
            if ($count > 0) {
                $search_result_list[] = $this->photo_map[$linux_file->getPhotoUuid()];
            }
        }

        return $search_result_list;
    }

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

        // TODO: Implement getPhotos() method.
        return [];
    }

    public function softDeletePhoto(string $photo_uuid): bool
    {
        // TODO: Implement softDeletePhoto() method.
        return true;
    }

    public function undoSoftDeletePhoto(string $photo_uuid): bool
    {
        // TODO: Implement undoSoftDeletePhoto() method.
        return true;
    }

    public function listPhotoCollections(int $limit): array
    {
        return [$this->photo_collection];
    }

    private function readPhotos()
    {
        if ($this->linux_file_map === null && $this->photo_map === null) {
            // TODO: Simple Linux Storage should have a upper limit
            $jpg_file_list = FolderHelper::listFilesRecursiveFromFolder($this->photo_path, '.jpg', ['.trash']);

            foreach ($jpg_file_list as $jpg_file) {
                $new_linux_file = LinuxFileFactory::createLinuxFile($jpg_file, $this->photo_path);
                $exif_data = ExifDataFactory::createExifData($new_linux_file->getFullFileNameAndPath($this->photo_path));
                $new_photo = PhotoFactory::createPhoto($new_linux_file, $exif_data);

                $this->linux_file_map[$new_linux_file->getPhotoUuid()] = $new_linux_file;
                $this->photo_map[$new_linux_file->getPhotoUuid()] = $new_photo;
            }
        }
    }
}