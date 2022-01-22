<?php

namespace PhotoCentralSimpleLinuxStorage\Factory;

use PhotoCentralSimpleLinuxStorage\Model\LinuxFile;
use PhotoCentralSimpleLinuxStorage\SimpleLinuxStorage;
use PhotoCentralStorage\Model\ExifData;
use PhotoCentralStorage\Photo;

/**
 * @internal
 */
class PhotoFactory
{
    public static function createPhoto(LinuxFile $linux_file, ExifData $exif_data): Photo
    {
        return new Photo(
            $linux_file->getPhotoUuid(),
            SimpleLinuxStorage::PHOTO_COLLECTION_UUID,
            $exif_data->getWidth(),
            $exif_data->getHeight(),
            $exif_data->getOrientation(),
            time(),
            $exif_data->getExifDateTime(),
            $linux_file->getLastModifiedDate(),
            null,
            $exif_data->getCameraBrand(),
            $exif_data->getCameraModel(),
        );
    }
}