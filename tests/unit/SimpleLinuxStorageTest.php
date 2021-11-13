<?php

namespace PhotoCentralSimpleLinuxStorage\Tests\unit;

use PhotoCentralSimpleLinuxStorage\Factory\PhotoUuidFactory;
use PhotoCentralSimpleLinuxStorage\SimpleLinuxStorage;
use PHPUnit\Framework\TestCase;

class SimpleLinuxStorageTest extends TestCase
{
    private SimpleLinuxStorage $simple_linux_storage;

    private const TEST_PHOTO_FILE_NAME = 'coffee-break.jpg';

    private function getPhotosTestFolder(): string {
        return dirname(__DIR__) . "/data/photos/";
    }

    public function setUp(): void
    {
        $this->simple_linux_storage = new SimpleLinuxStorage($this->getPhotosTestFolder(),'b');
    }

    public function testListPhotoCollections()
    {
        $photo_collection_list = $this->simple_linux_storage->listPhotoCollections(2);

        $this->assertCount(1, $photo_collection_list, 'One item in the list is expected');
        $this->assertEquals('1', $photo_collection_list[0]->getId(), 'id is expected to be 1');
        $this->assertEquals('Photo folder', $photo_collection_list[0]->getName(), 'name is expected to be "Photo folder"');
        $photo_folder = $this->getPhotosTestFolder();
        $this->assertEquals("Simple Linux Storage folder ($photo_folder)", $photo_collection_list[0]->getDescription());
    }

    public function testGetPhoto()
    {
        $test_photo_file = self::getPhotosTestFolder() . self::TEST_PHOTO_FILE_NAME;

        $photo_uuid = PhotoUuidFactory::generatePhotoUuid($test_photo_file);

        $photo = $this->simple_linux_storage->getPhoto($photo_uuid);

        $this->assertEquals('ASUS', $photo->getCameraBrand());
    }

    public function testListPhotos()
    {
        $photo_list = $this->simple_linux_storage->listPhotos(0, time(), '', 100);

        $this->assertCount(9, $photo_list, '9 photos should be listed');
    }

    public function testSearch()
    {
        $search_result_list = $this->simple_linux_storage->searchPhotos('ball');
        $this->assertCount(3, $search_result_list, 'three images should be found');

        $search_result_list = $this->simple_linux_storage->searchPhotos('coffee');
        $this->assertCount(1, $search_result_list, 'one image should be found');
    }
}

