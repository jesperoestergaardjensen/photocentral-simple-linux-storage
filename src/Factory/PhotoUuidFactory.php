<?php

namespace PhotoCentralSimpleLinuxStorage\Factory;

/**
 * @internal
 */
class PhotoUuidFactory
{
    public static function generatePhotoUuid(string $full_photo_file_name_with_path)
    {
        return md5_file($full_photo_file_name_with_path);
    }
}
