<?php

namespace PhotoCentralSimpleLinuxStorage\Model;

/**
 * @internal
 */
class LinuxFile
{
    private int $inode_index;
    private string $file_name;
    private string $file_path;
    private string $photo_collection_id;
    private int $last_modified_date;
    private string $photo_uuid;

    public function __construct(int $inode_index, string $file_name, string $file_path, int $last_modified_date, string $photo_collection_id, string $photo_uuid)
    {
        $this->inode_index = $inode_index;
        $this->file_name = $file_name;
        $this->file_path = $file_path;
        $this->last_modified_date = $last_modified_date;
        $this->photo_collection_id = $photo_collection_id;
        $this->photo_uuid = $photo_uuid;
    }

    public function getPhotoUuid(): string
    {
        return $this->photo_uuid;
    }

    public function getFullFileNameAndPath(string $base_path): string
    {
        return $base_path . $this->file_path . $this->file_name;
    }

    public function getLastModifiedDate(): int
    {
        return $this->last_modified_date;
    }

    public function getFileName(): string
    {
        return $this->file_name;
    }

    public function getFilePath(): string
    {
        return $this->file_path;
    }
}
