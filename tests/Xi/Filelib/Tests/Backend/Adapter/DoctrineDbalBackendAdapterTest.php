<?php

namespace Xi\Filelib\Tests\Backend\Adapter;

use Xi\Filelib\Backend\Adapter\DoctrineDbalBackendAdapter;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @group backend
 */
class DoctrineDbalBackendAdapterTest extends RelationalDbTestCase
{

    /**
     * @return DoctrineDbalBackendAdapter
     */
    protected function setUpBackend()
    {
        $this->conn = DriverManager::getConnection(
            array(
                'driver' => 'pdo_' . PDO_DRIVER,
                'dbname' => PDO_DBNAME,
                'user' => PDO_USERNAME,
                'password' => PDO_PASSWORD,
                'host' => PDO_HOST,
            )
        );
        return new DoctrineDbalBackendAdapter($this->conn);
    }

    /**
     * @test
     */
    public function failsWhenPlatformIsNotSupported()
    {
        $this->expectException('RuntimeException');

        $conn = $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
        $platform = $this->getMockBuilder('Doctrine\DBAL\Platforms\AbstractPlatform')->getMockForAbstractClass();

        $conn->expects($this->any())->method('getDatabasePlatform')->will($this->returnValue($platform));

        $p = new DoctrineDbalBackendAdapter($conn);
    }
}
