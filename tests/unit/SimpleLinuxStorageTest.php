<?php

namespace PhotoCentralSimpleLinuxStorage\Tests\unit;

use PhotoCentralSimpleLinuxStorage\Factory\PhotoUuidFactory;
use PhotoCentralSimpleLinuxStorage\SimpleLinuxStorage;
use PhotoCentralStorage\Exception\PhotoCentralStorageException;
use PhotoCentralStorage\Model\ImageDimensions;
use PhotoCentralStorage\Model\PhotoFilter\PhotoCollectionIdFilter;
use PhotoCentralStorage\Model\PhotoFilter\CreatedTimestampRangeFilter;
use PhotoCentralStorage\Model\PhotoFilter\PhotoDateTimeRangeFilter;
use PhotoCentralStorage\Model\PhotoFilter\PhotoUuidFilter;
use PhotoCentralStorage\Model\PhotoQuantity\PhotoQuantityDay;
use PhotoCentralStorage\Model\PhotoQuantity\PhotoQuantityMonth;
use PhotoCentralStorage\Model\PhotoQuantity\PhotoQuantityYear;
use PhotoCentralStorage\Model\PhotoSorting\BasicSorting;
use PhotoCentralStorage\Model\PhotoSorting\SortByPhotoDateTime;
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
        $this->assertEquals($this->simple_linux_storage->getPhotoCollectionUuid(), $photo_collection_list[0]->getId(), 'id is expected to be ' . $this->simple_linux_storage->getPhotoCollectionUuid());
        $this->assertEquals('Photo folder', $photo_collection_list[0]->getName(),
            'name is expected to be "Photo folder"');
        $photo_folder = $this->getPhotosTestFolder();
        $this->assertEquals("Simple Linux Storage folder ($photo_folder)", $photo_collection_list[0]->getDescription());
    }

    public function testGetPhoto()
    {
        $test_photo_file = self::getPhotosTestFolder() . self::TEST_PHOTO_FILE_NAME_1;

        $photo_uuid = PhotoUuidFactory::generatePhotoUuid($test_photo_file);

        $photo = $this->simple_linux_storage->getPhoto($photo_uuid, $this->simple_linux_storage->getPhotoCollectionUuid());

        $this->assertEquals('ASUS', $photo->getCameraBrand());
        $this->assertEquals(386, $photo->getHeight());
        $this->assertEquals(686, $photo->getWidth());

        $this->expectException(PhotoCentralStorageException::class);
        $this->simple_linux_storage->getPhoto('non-existing-uuid', $this->simple_linux_storage->getPhotoCollectionUuid());
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
        $this->assertCount(11, $photo_list, '11 photos should be listed');
    }

    public function testListPhotosCaseC()
    {
        // Test filter that limits to time period
        $photo_list = $this->simple_linux_storage->listPhotos(
            [
                new CreatedTimestampRangeFilter(strtotime('01-10-2021 00:00:00'), strtotime('20-11-2022 00:00:00'))
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
                new PhotoDateTimeRangeFilter(strtotime('01-10-2021 00:00:00'), strtotime('20-11-2022 00:00:00')),
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
                new PhotoDateTimeRangeFilter(strtotime('01-10-2021 00:00:00'), strtotime('20-11-2022 00:00:00')),
                new PhotoCollectionIdFilter([$this->simple_linux_storage->getPhotoCollectionUuid()]),
            ],
            [
                new SortByPhotoDateTime(BasicSorting::DESC)
            ],
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
        $search_result_list = $this->simple_linux_storage->searchPhotos('ball', [$this->simple_linux_storage->getPhotoCollectionUuid()]);
        $this->assertCount(3, $search_result_list, 'three images should be found');

        $search_result_list = $this->simple_linux_storage->searchPhotos('coffee', [$this->simple_linux_storage->getPhotoCollectionUuid()]);
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
        $photo_path = $this->simple_linux_storage->getPathOrUrlToPhoto($photo_uuid_1, ImageDimensions::createFromId(ImageDimensions::THUMB_ID), null);

        $expected_path = $this->getImageCacheTestFolder() . ImageDimensions::THUMB_ID . DIRECTORY_SEPARATOR . "$photo_uuid_1.jpg";
        $this->assertEquals($expected_path, $photo_path);

        // Clean up after test
        unlink($photo_path);
        rmdir($this->getImageCacheTestFolder() . ImageDimensions::THUMB_ID . DIRECTORY_SEPARATOR);
    }

    public function testlistPhotoQuantityByYear()
    {
        $expected = [
            new PhotoQuantityYear('2022',2022, 6),
            new PhotoQuantityYear('2021',2021, 1),
            new PhotoQuantityYear('2020',2020, 3),
        ];

        $actual = $this->simple_linux_storage->listPhotoQuantityByYear([$this->simple_linux_storage->getPhotoCollectionUuid()]);
        $this->assertEquals($expected, $actual);
    }

    public function testlistPhotoQuantityByMonth()
    {
        $expected = [
            new PhotoQuantityMonth('02',2, 1),
            new PhotoQuantityMonth('09',9, 2),
        ];

        $actual = $this->simple_linux_storage->listPhotoQuantityByMonth(2020, [$this->simple_linux_storage->getPhotoCollectionUuid()]);
        $this->assertEquals($expected, $actual);
    }

    public function testlistPhotoQuantityByDay()
    {
        $expected = [
            new PhotoQuantityDay('06',6, 1),
            new PhotoQuantityDay('18',18, 1),
        ];

        $actual = $this->simple_linux_storage->listPhotoQuantityByDay(9, 2020, [$this->simple_linux_storage->getPhotoCollectionUuid()]);
        $this->assertEquals($expected, $actual);
    }
}

