<?php

namespace PhotoCentralSimpleLinuxStorage\Factory;

use Exception;
use PhotoCentralSimpleLinuxStorage\Model\LinuxFile;
use PhotoCentralSimpleLinuxStorage\SimpleLinuxStorage;
use PhotoCentralStorage\Exception\PhotoCentralStorageException;

/**
 * @internal
 */
class LinuxFileFactory
{
    private const INODE_INDEX_COLUMN_NUMBER = 0;
    private const MODIFY_DATE_COLUMN_NUMBER = 1;
    private const FULL_FILE_NAME_AND_PATH_COLUMN_NUMBER = 2;

    /**
     * @param string $file_info
     * @param string $base_path
     *
     * @return LinuxFile
     * @throws Exception
     */
    public static function createLinuxFile(string $file_info, string $base_path): LinuxFile
    {
        // TODO : Check input format is correct

        /* Get filename and inodeIndex */
        $explodedFileInfo = explode(';', trim($file_info));
        $inodeIndex = $explodedFileInfo[self::INODE_INDEX_COLUMN_NUMBER];
        $fileModifyDate = $explodedFileInfo[self::MODIFY_DATE_COLUMN_NUMBER];
        $full_file_name_and_path = $explodedFileInfo[self::FULL_FILE_NAME_AND_PATH_COLUMN_NUMBER];
        $filenameParts = pathinfo($full_file_name_and_path);

        if (is_file($full_file_name_and_path) === false) {
            throw new PhotoCentralStorageException("$full_file_name_and_path do not appear to be a valid file");
        }

        return new LinuxFile(
            $inodeIndex,
            $filenameParts['basename'],
            self::generate_file_path($filenameParts['dirname'], $base_path),
            strtotime($fileModifyDate),
            SimpleLinuxStorage::PHOTO_COLLECTION_ID,
            PhotoUuidFactory::generatePhotoUuid($full_file_name_and_path),
        );
    }

    /**
     * Removes base path from complete path and adjust slashed
     *
     * @param string $complete_file_path
     * @param string $base_path
     *
     * @return string
     */
    private static function generate_file_path(string $complete_file_path, string $base_path): string
    {
        // Strip trailing '/'
        $adjusted_image_source_path = rtrim($base_path, '/');

        // Remove source_path from file_path
        $file_path = str_replace($adjusted_image_source_path, '', $complete_file_path);

        // If "extra" path found move slash from beginning to end to match rest of the system
        if ($file_path !== '') {
            $file_path = ltrim($file_path, '/') . DIRECTORY_SEPARATOR;
        }

        return $file_path;
    }
}
