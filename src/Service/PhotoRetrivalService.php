<?php

namespace PhotoCentralSimpleLinuxStorage\Service;

use LinuxImageHelper\Exception\LinuxImageHelperException;
use LinuxImageHelper\Service\JpgImageService;
use PhotoCentralSimpleLinuxStorage\Model\LinuxFile;
use PhotoCentralStorage\Exception\PhotoCentralStorageException;
use PhotoCentralStorage\Model\ImageDimensions;

class PhotoRetrivalService
{
    private string $image_cache_path;
    private string $photo_path;

    public function __construct(string $photo_path, string $image_cache_path)
    {
        $this->image_cache_path = $image_cache_path;
        $this->photo_path = $photo_path;
    }

    /**
     * @param LinuxFile       $linux_file
     * @param ImageDimensions $image_dimensions
     *
     * @return string
     * @throws PhotoCentralStorageException
     * @throws LinuxImageHelperException
     */
    public function getPhotoPath(LinuxFile $linux_file, ImageDimensions $image_dimensions) : string
    {
        $photo_path = self::getResizedPath($image_dimensions->getId(), $linux_file->getPhotoUuid());

        if (file_exists($photo_path)) {
            return $photo_path;
        }

        $org_file_name_and_path = $linux_file->getFullFileNameAndPath($this->photo_path);

        if (is_readable($org_file_name_and_path) === false) {
            throw new PhotoCentralStorageException("Cannot read the file $org_file_name_and_path, (photo_uuid={$linux_file->getPhotoUuid()}. Please check file and folder permissions");
        }

        $this->resize($linux_file->getPhotoUuid(), $org_file_name_and_path, $image_dimensions);
        $photo_path = $this->getResizedPath($image_dimensions->getId(), $linux_file->getPhotoUuid());

        if (file_exists($photo_path)) {
            return $photo_path;
        }

        throw new PhotoCentralStorageException('Cannot resolve photo path on image with unique id = ' . $linux_file->getPhotoUuid());
    }

    /**
     * Will check if needed image cache folder exists and if not try to create
     *
     * @param string $dimensions_id
     *
     * @throws PhotoCentralStorageException
     */
    private function imageCacheFolderCheck(string $dimensions_id)
    {
        $path = $this->image_cache_path . $dimensions_id;
        if (! file_exists($path)) {
            if (mkdir($path, 0777, true) === false) {
                throw new PhotoCentralStorageException("Could not create nessesary folder: " . $path);
            }
        }
    }

    /**
     * @param string          $photo_uuid
     * @param string          $org_file_name_and_path
     * @param ImageDimensions $dimensions
     *
     * @return array
     * @throws PhotoCentralStorageException
     * @throws LinuxImageHelperException
     */
    public function resize(string $photo_uuid, string $org_file_name_and_path, ImageDimensions $dimensions): array
    {
        $jpg_image = (new JpgImageService)->createJpg($org_file_name_and_path);
        $ratio = round($jpg_image->getWidth() / $jpg_image->getHeight(), 2);

        if ($ratio === 0) {
            throw new PhotoCentralStorageException("The image with unique id = '$photo_uuid' has a illegal ratio = $ratio. Original file name and path = $org_file_name_and_path");
        } else if ($ratio > 1) {
            $jpg_image = (new JpgImageService)->resizeToWidth($jpg_image, $dimensions->getWidth());
        } else {
            $jpg_image = (new JpgImageService)->resizeToHeight($jpg_image, $dimensions->getHeight());
        }

        // Check path exists
        $this->imageCacheFolderCheck($dimensions->getId());

        // Save result to disc
        (new JpgImageService())->saveJpgToDisc($jpg_image, $this->getResizedPath($dimensions->getId(), $photo_uuid));

        // return final dimensions
        return ['width' => $jpg_image->getWidth(), 'height' => $jpg_image->getHeight()];
    }

    private function getResizedPath(string $dimensions_id, string $photo_uuid) : string
    {
        return $this->image_cache_path . $dimensions_id . DIRECTORY_SEPARATOR . $photo_uuid . '.jpg';
    }
}