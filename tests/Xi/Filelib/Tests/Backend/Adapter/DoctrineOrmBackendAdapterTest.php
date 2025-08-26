<?php

namespace Xi\Filelib\Tests\Backend\Adapter;

use Doctrine\DBAL\DriverManager;
use Xi\Filelib\Backend\Adapter\DoctrineOrmBackendAdapter;
use Xi\Filelib\Folder\Folder;
use Xi\Filelib\Resource\Resource;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * @group backend
 * @group doctrine
 */
class DoctrineOrmBackendAdapterTest extends RelationalDbTestCase
{

    /**
     * @return DoctrineOrmBackendAdapter
     */
    protected function setUpBackend()
    {
        $config = ORMSetup::createAnnotationMetadataConfiguration(
            [ROOT_TESTS . '/../library/Xi/Filelib/Backend/DoctrineOrm/Entity'],
            true, // isDevMode
            false // useSimpleAnnotationReader
        );
        $config->setProxyDir(ROOT_TESTS . '/data/temp');
        $config->setProxyNamespace('FilelibTest\Proxies');

        $connectionOptions = PDO_DRIVER === 'sqlite'
            ? array(
                'driver' => 'pdo_' . PDO_DRIVER,
                'path' => PDO_DBNAME,
            )
            : array(
                'driver' => 'pdo_' . PDO_DRIVER,
                'dbname' => PDO_DBNAME,
                'user' => PDO_USERNAME,
                'password' => PDO_PASSWORD,
                'host' => PDO_HOST,
            );

        $this->conn = DriverManager::getConnection($connectionOptions, $config);
        $em = new EntityManager($this->conn, $config);

        return new DoctrineOrmBackendAdapter($em);
    }

    /**
     * @test
     */
    public function entityClassGettersShouldReturnCorrectClassNames()
    {
        $this->setUpEmptyDataSet();

        $this->assertEquals(
            'Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\File',
            $this->backend->getFileEntityName()
        );

        $this->assertEquals(
            'Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\Folder',
            $this->backend->getFolderEntityName()
        );

        $this->assertEquals(
            'Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\Resource',
            $this->backend->getResourceEntityName()
        );
    }

    /**
     * @test
     */
    public function deleteFolderReturnsFalseOnEntityNotFound()
    {
        $this->setUpEmptyDataSet();

        $em = $this->createEntityManagerMock();
        $em->expects($this->once())
            ->method('find')
            ->will($this->returnValue(null));

        $backend = new DoctrineOrmBackendAdapter($em);

        $resource = Folder::create(
            array(
                'id' => 666,
                'parent_id' => null,
                'name' => 'foo',
            )
        );

        $this->assertFalse($backend->deleteFolder($resource));
    }

    /**
     * @test
     */
    public function deleteResourceReturnsFalseOnEntityNotFound()
    {
        $this->setUpEmptyDataSet();

        $em = $this->createEntityManagerMock();
        $em->expects($this->once())
            ->method('find')
            ->will($this->returnValue(null));

        $backend = new DoctrineOrmBackendAdapter($em);

        $resource = Resource::create(array('id' => 1));

        $this->assertFalse($backend->deleteResource($resource));
    }

    /**
     * @param EntityManager $em
     */
    private function returnEmptyArrayForFindFilesInFolder(EntityManager $em)
    {
        $repository = $this->getMockAndDisableOriginalConstructor(
            'Doctrine\ORM\EntityRepository'
        );
        $repository
            ->expects($this->once())
            ->method('findBy')
            ->will($this->returnValue(array()));

        $em
            ->expects($this->once())
            ->method('getRepository')
            ->will($this->returnValue($repository));
    }


    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createEntityManagerMock()
    {
        return $this->getMockAndDisableOriginalConstructor(
            'Doctrine\ORM\EntityManager'
        );
    }

    /**
     * @test
     */
    public function entityManagerGetterShouldWork()
    {
        $em = $this->createEntityManagerMock();
        $platform = new DoctrineOrmBackendAdapter($em);

        $this->assertSame($em, $platform->getEntityManager());
    }
}
