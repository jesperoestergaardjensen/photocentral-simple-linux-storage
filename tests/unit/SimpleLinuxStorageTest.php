<?php

namespace PhotoCentralSimpleLinuxStorage\Tests\unit;

use PhotoCentralSimpleLinuxStorage\Factory\PhotoUuidFactory;
use PhotoCentralSimpleLinuxStorage\SimpleLinuxStorage;
use PhotoCentralStorage\Exception\PhotoCentralStorageException;
use PhotoCentralStorage\Model\ImageDimensions;
use PhotoCentralStorage\Model\PhotoFilter\PhotoCollectionIdFilter;
use PhotoCentralStorage\Model\PhotoFilter\CreatedTimestampRangeFilter;
use PhotoCentralStorage\Model\PhotoFilter\PhotoUuidFilter;
use PhotoCentralStorage\Model\PhotoSorting\BasicSorting;
use PhotoCentralStorage\Model\PhotoSorting\SortByCreatedTimestamp;
use PHPUnit\Framework\TestCase;

class SimpleLinuxStorageTest extends TestCase
{
    private SimpleLinuxStorage $simple_linux_storage;

    private const DELETED_PHOTO_UUID_1 = 'e9be5e89fc397c680580599e1f3ef21e';
    private const DELETED_PHOTO_UUID_2 = '8fae4586a47a2356df0ea12c997e047e';

    private const TEST_PHOTO_FILE_NAME_1 = 'coffee-break.jpg';
    private const TEST_PHOTO_FILE_NAME_2 = 'sport/mtb/mountain-bike-g30008f9d7_1280.jpg';

    private function getPhotosTestFolder(): string
    {
        return dirname(__DIR__) . "/data/photos/";
    }

    private function getImageCacheTestFolder(): string
    {
        return dirname(__DIR__) . "/data/image_cache/";
    }

    public function setUp(): void
    {
        $this->simple_linux_storage = new SimpleLinuxStorage($this->getPhotosTestFolder(),
            $this->getImageCacheTestFolder());
    }

    public function testListPhotoCollections()
    {
        $photo_collection_list = $this->simple_linux_storage->listPhotoCollections(2);

        $this->assertCount(1, $photo_collection_list, 'One item in the list is expected');
        $this->assertEquals(SimpleLinuxStorage::PHOTO_COLLECTION_UUID, $photo_collection_list[0]->getId(), 'id is expected to be ' . SimpleLinuxStorage::PHOTO_COLLECTION_UUID);
        $this->assertEquals('Photo folder', $photo_collection_list[0]->getName(),
            'name is expected to be "Photo folder"');
        $photo_folder = $this->getPhotosTestFolder();
        $this->assertEquals("Simple Linux Storage folder ($photo_folder)", $photo_collection_list[0]->getDescription());
    }

    public function testGetPhoto()
    {
        $test_photo_file = self::getPhotosTestFolder() . self::TEST_PHOTO_FILE_NAME_1;

        $photo_uuid = PhotoUuidFactory::generatePhotoUuid($test_photo_file);

        $photo = $this->simple_linux_storage->getPhoto($photo_uuid);

        $this->assertEquals('ASUS', $photo->getCameraBrand());
        $this->assertEquals(386, $photo->getHeight());
        $this->assertEquals(686, $photo->getWidth());

        $this->expectException(PhotoCentralStorageException::class);
        $this->simple_linux_storage->getPhoto('non-existing-uuid');
    }

    public function testListPhotosCaseA()
    {
        // Simple listing
        $photo_list = $this->simple_linux_storage->listPhotos(
            null,
            null,
            25
        );
        // TODO test that it is the correct photos returned
        $this->assertCount(9, $photo_list, '9 photos should be listed');
    }

    public function testListPhotosCaseB()
    {
        // Test the sorting
        $photo_list = $this->simple_linux_storage->listPhotos(
            null,
            new SortByCreatedTimestamp(BasicSorting::DESC),
            25
        );

        // TODO test that it is the correct photos returned
        $this->assertCount(9, $photo_list, '9 photos should be listed');
    }

    public function testListPhotosCaseC()
    {
        // Test filter that limits to time period
        $photo_list = $this->simple_linux_storage->listPhotos(
            [
                new CreatedTimestampRangeFilter(strtotime('01-10-2021 00:00:00'), strtotime('20-11-2021 00:00:00'))
            ],
            null,
            25
        );

        // TODO test that it is the correct photos returned
        $this->assertCount(7, $photo_list, '7 photos should be listed');
    }

    public function testListPhotosCaseD()
    {
        // Test filter that limits to time period
        $photo_list = $this->simple_linux_storage->listPhotos(
            [
                new CreatedTimestampRangeFilter(strtotime('01-10-2021 00:00:00'), strtotime('20-11-2021 00:00:00')),
                new PhotoCollectionIdFilter(['do-not-exist']),
            ],
            null,
            25
        );

        // TODO test that it is the correct photos returned
        $this->assertCount(0, $photo_list, '0 photos should be listed');

        // Test filter that limits to time period
        $photo_list = $this->simple_linux_storage->listPhotos(
            [
                new CreatedTimestampRangeFilter(strtotime('01-10-2021 00:00:00'), strtotime('20-11-2021 00:00:00')),
                new PhotoCollectionIdFilter([SimpleLinuxStorage::PHOTO_COLLECTION_UUID]),
            ],
            null,
            25
        );

        // TODO test that it is the correct photos returned
        $this->assertCount(7, $photo_list, '7 photos should be listed');
    }

    public function testListPhotosCaseE()
    {
        $test_photo_file_1 = self::getPhotosTestFolder() . self::TEST_PHOTO_FILE_NAME_1;
        $test_photo_file_2 = self::getPhotosTestFolder() . self::TEST_PHOTO_FILE_NAME_2;

        $photo_uuid_1 = PhotoUuidFactory::generatePhotoUuid($test_photo_file_1);
        $photo_uuid_2 = PhotoUuidFactory::generatePhotoUuid($test_photo_file_2);

        // Test that list method with a list of photo uuid's
        $photo_list = $this->simple_linux_storage->listPhotos(
            [
                new PhotoUuidFilter([$photo_uuid_1, $photo_uuid_2])
            ],
            null,
            25
        );

        $this->assertCount(2, $photo_list);
    }

    public function testSearch()
    {
        $search_result_list = $this->simple_linux_storage->searchPhotos('ball');
        $this->assertCount(3, $search_result_list, 'three images should be found');

        $search_result_list = $this->simple_linux_storage->searchPhotos('coffee');
        $this->assertCount(1, $search_result_list, 'one image should be found');
    }

    public function testSoftDelete()
    {
        $this->assertTrue($this->simple_linux_storage->softDeletePhoto(self::DELETED_PHOTO_UUID_1));
        $this->assertTrue($this->simple_linux_storage->softDeletePhoto(self::DELETED_PHOTO_UUID_2));
    }

    /**
     * @depends testSoftDelete
     */
    public function testUndoSoftDeletePhoto()
    {
        $this->assertTrue($this->simple_linux_storage->undoSoftDeletePhoto(self::DELETED_PHOTO_UUID_1));
        $this->assertTrue($this->simple_linux_storage->undoSoftDeletePhoto(self::DELETED_PHOTO_UUID_2));
    }

    public function testGetPhotoPath()
    {
        $test_photo_file_1 = self::getPhotosTestFolder() . self::TEST_PHOTO_FILE_NAME_1;
        $photo_uuid_1 = PhotoUuidFactory::generatePhotoUuid($test_photo_file_1);
        $photo_path = $this->simple_linux_storage->getPhotoPath($photo_uuid_1, ImageDimensions::createThumb());

        $expected_path = $this->getImageCacheTestFolder() . ImageDimensions::THUMB_ID . DIRECTORY_SEPARATOR . "$photo_uuid_1.jpg";
        $this->assertEquals($expected_path, $photo_path);

        // Clean up after test
        unlink($photo_path);
        rmdir($this->getImageCacheTestFolder() . ImageDimensions::THUMB_ID . DIRECTORY_SEPARATOR);
    }
}
