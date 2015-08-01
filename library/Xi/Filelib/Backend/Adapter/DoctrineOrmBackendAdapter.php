<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Backend\Adapter;

use ArrayIterator;
use Doctrine\Common\Util\Debug;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Iterator;
use Xi\Filelib\Backend\FindByIdsRequest;
use Xi\Filelib\File\File;
use Xi\Filelib\Folder\Folder;
use Xi\Filelib\Resource\ConcreteResource;
use Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\Versioned;
use Xi\Filelib\Versionable\Versionable;

/**
 * Doctrine 2 backend for filelib
 *
 * @category Xi
 * @package  Filelib
 * @author   Mikko Hirvonen <mikko.petteri.hirvonen@gmail.com>
 * @author   pekkis
 */
class DoctrineOrmBackendAdapter extends BaseDoctrineBackendAdapter implements BackendAdapter
{
    /**
     * @var string
     */
    private $fileEntityName = 'Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\File';

    /**
     * @var string
     */
    private $folderEntityName = 'Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\Folder';

    /**
     * @var string
     */
    private $resourceEntityName = 'Xi\Filelib\Backend\Adapter\DoctrineOrm\Entity\Resource';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Returns the fully qualified file entity classname
     *
     * @return string
     */
    public function getFileEntityName()
    {
        return $this->fileEntityName;
    }

    /**
     * Returns the entity manager
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * Returns the fully qualified folder entity classname
     *
     * @return string
     */
    public function getFolderEntityName()
    {
        return $this->folderEntityName;
    }

    /**
     * Returns the fully qualified resource entity classname
     *
     * @return string
     */
    public function getResourceEntityName()
    {
        return $this->resourceEntityName;
    }

    /**
     * @see BackendAdapter::updateFile
     */
    public function updateFile(File $file)
    {
        $entity = $this->getFileReference($file);
        $entity->setFolder($this->getFolderReference($file->getFolderId()));
        $entity->setProfile($file->getProfile());
        $entity->setName($file->getName());
        $entity->setDateCreated($file->getDateCreated());
        $entity->setStatus($file->getStatus());
        $entity->setUuid($file->getUuid());
        $entity->setResource($this->em->getReference($this->getResourceEntityName(), $file->getResource()->getId()));
        $entity->setData($file->getData()->toArray());

        $this->em->flush($entity);
        return true;
    }

    /**
     * @see BackendAdapter::deleteFile
     */
    public function deleteFile(File $file)
    {
        if (!$entity = $this->em->find($this->fileEntityName, $file->getId())) {
            return false;
        }

        $this->em->remove($entity);
        $this->em->flush($entity);

        return true;
    }

    /**
     * @see BackendAdapter::createFolder
     */
    public function createFolder(Folder $folder)
    {
        $folderEntity = new $this->folderEntityName();

        if ($folder->getParentId()) {
            $folderEntity->setParent($this->getFolderReference($folder->getParentId()));
        }

        $folderEntity->setName($folder->getName());
        $folderEntity->setUrl($folder->getUrl());
        $folderEntity->setUuid($folder->getUuid());
        $folderEntity->setData($folder->getData()->toArray());

        $this->em->persist($folderEntity);
        $this->em->flush($folderEntity);

        $folder->setId($folderEntity->getId());

        return $folder;
    }

    /**
     * @see BackendAdapter::updateFolder
     */
    public function updateFolder(Folder $folder)
    {
        try {
            $folderRow = $this->getFolderReference($folder->getId());

            if ($folder->getParentId()) {
                $folderRow->setParent(
                    $this->getFolderReference($folder->getParentId())
                );
            } else {
                $folderRow->removeParent();
            }

            $folderRow->setName($folder->getName());
            $folderRow->setUrl($folder->getUrl());
            $folderRow->setUuid($folder->getUuid());
            $folderRow->setData($folder->getData()->toArray());

            $this->em->flush($folderRow);

            return true;
        } catch (EntityNotFoundException $e) {
            return false;
        }
    }

    /**
     * @see BackendAdapter::updateResource
     */
    public function updateResource(ConcreteResource $resource)
    {
        try {
            $resourceRow = $this->em->getReference($this->getResourceEntityName(), $resource->getId());
            $resourceRow->setUuid($resource->getUuid());
            $resourceRow->setData($resource->getData()->toArray());
            $resourceRow->setHash($resource->getHash());
            $this->em->flush($resourceRow);

            return true;
        } catch (EntityNotFoundException $e) {
            return false;
        }
    }

    /**
     * @see BackendAdapter::deleteFolder
     */
    public function deleteFolder(Folder $folder)
    {
        try {
            $folderEntity = $this->em->find($this->folderEntityName, $folder->getId());

            if (!$folderEntity) {
                return false;
            }

            $this->em->remove($folderEntity);
            $this->em->flush($folderEntity);

            return true;
        } catch (EntityNotFoundException $e) {
            return false;
        }
    }

    /**
     * @see BackendAdapter::deleteResource
     */
    public function deleteResource(ConcreteResource $resource)
    {
        try {
            $entity = $this->em->find($this->resourceEntityName, $resource->getId());

            if (!$entity) {
                return false;
            }

            $this->em->remove($entity);
            $this->em->flush($entity);

            return true;
        } catch (EntityNotFoundException $e) {
            return false;
        }
    }

    /**
     * @see BackendAdapter::createResource
     */
    public function createResource(ConcreteResource $resource)
    {
        $resourceRow = new $this->resourceEntityName();
        $resourceRow->setUuid($resource->getUuid());
        $resourceRow->setHash($resource->getHash());
        $resourceRow->setDateCreated($resource->getDateCreated());
        $resourceRow->setMimetype($resource->getMimetype());
        $resourceRow->setSize($resource->getSize());
        $this->em->persist($resourceRow);
        $this->em->flush($resourceRow);
        $resource->setId($resourceRow->getId());

        return $resource;
    }

    /**
     * @see BackendAdapter::createFile
     */
    public function createFile(File $file, Folder $folder)
    {
        $self = $this;

        return $this->em->transactional(
            function (EntityManager $em) use ($self, $file, $folder) {
                $fileEntityName = $self->getFileEntityName();

                $entity = new $fileEntityName;
                $entity->setFolder($self->getFolderReference($folder->getId()));
                $entity->setName($file->getName());
                $entity->setProfile($file->getProfile());
                $entity->setDateCreated($file->getDateCreated());
                $entity->setStatus($file->getStatus());
                $entity->setUuid($file->getUuid());
                $entity->setData($file->getData()->toArray());

                $resource = $file->getResource();
                if ($resource) {
                    $entity->setResource($em->getReference($self->getResourceEntityName(), $resource->getId()));
                }

                $em->persist($entity);
                $em->flush($entity);

                $file->setId($entity->getId());
                $file->setFolderId($entity->getFolder()->getId());

                return $file;
            }
        );
    }

    /**
     * @see BackendAdapter::getNumberOfReferences
     */
    public function getNumberOfReferences(ConcreteResource $resource)
    {
        return $this->em
            ->getConnection()
            ->fetchColumn(
                "SELECT COUNT(id) FROM xi_filelib_file WHERE resource_id = ?",
                array(
                    $resource->getId()
                )
            );
    }

    /**
     * @see BackendAdapter::findByIds
     */
    public function findByIds(FindByIdsRequest $request)
    {
        if ($request->isFulfilled()) {
            return $request;
        }

        $ids = $request->getNotFoundIds();
        $className = $request->getClassName();

        $resources = $this->classNameToResources[$className];
        $repo = $this->em->getRepository($this->$resources['getEntityName']());
        $rows = $repo->findBy(
            array(
                'id' => $ids
            )
        );

        $rows = new ArrayIterator($rows);
        return $request->foundMany($this->$resources['exporter']($rows));
    }

    /**
     * @param  Iterator      $iter
     * @return ArrayIterator
     */
    protected function exportFolders(Iterator $iter)
    {
        $ret = new ArrayIterator(array());
        foreach ($iter as $folder) {
            $ret->append(
                Folder::create(
                    array(
                        'id' => $folder->getId(),
                        'parent_id' => $folder->getParent() ? $folder->getParent()->getId() : null,
                        'name' => $folder->getName(),
                        'url' => $folder->getUrl(),
                        'uuid' => $folder->getUuid(),
                        'data' => $folder->getData(),
                    )
                )
            );
        }

        return $ret;
    }

    /**
     * @param  Iterator      $iter
     * @return ArrayIterator
     */
    protected function exportFiles(Iterator $iter)
    {
        $ret = new ArrayIterator(array());
        foreach ($iter as $file) {

            $resources = new ArrayIterator(array($file->getResource()));

            $file = File::create(
                array(
                    'id' => $file->getId(),
                    'folder_id' => $file->getFolder() ? $file->getFolder()->getId() : null,
                    'profile' => $file->getProfile(),
                    'name' => $file->getName(),
                    'date_created' => $file->getDateCreated(),
                    'status' => $file->getStatus(),
                    'uuid' => $file->getUuid(),
                    'resource' => $this->exportResources($resources)->current(),
                    'data' => $file->getData(),
                )
            );

            $this->setVersions($file);

            $ret->append(
                $file
            );
        }

        return $ret;
    }

    /**
     * @param  Iterator      $iter
     * @return ArrayIterator
     */
    protected function exportResources(Iterator $iter)
    {
        $ret = new ArrayIterator(array());
        foreach ($iter as $resource) {
            $ret->append(
                ConcreteResource::create(
                    array(
                        'id' => $resource->getId(),
                        'uuid' => $resource->getUuid(),
                        'hash' => $resource->getHash(),
                        'date_created' => $resource->getDateCreated(),
                        'data' => $resource->getData(),
                        'mimetype' => $resource->getMimetype(),
                        'size' => $resource->getSize(),
                    )
                )
            );
        }

        return $ret;
    }

    /**
     * @param  File        $file
     * @return object|null
     */
    public function getFileReference(File $file)
    {
        return $this->em->getReference($this->fileEntityName, $file->getId());
    }

    /**
     * @param  integer     $id
     * @return object|null
     */
    public function getFolderReference($id)
    {
        return $this->em->getReference($this->folderEntityName, $id);
    }

    /**
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->em->getConnection();
    }

    protected function setVersions(Versionable $versionable)
    {
        $versions = $this->em->getRepository(Versioned::class)->findBy([
            'uuid' => $versionable->getUuid()
        ]);

        foreach ($versions as $v) {
            /** @var Versioned $v */
            $versionable->addVersion(
                new Versioned(
                    $v->getUuid(),
                    $v->getVersion()
                ),
                $this->exportResources(new ArrayIterator([$v->getResource()]))->current()
            );
        }

    }
}
