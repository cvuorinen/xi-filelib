<?php

namespace Xi\Filelib\Folder;

use Xi\Filelib\FileLibrary;
use Xi\Filelib\AbstractOperator;
use Xi\Filelib\FilelibException;
use Xi\Filelib\File\File;
use Xi\Filelib\Folder\Folder;
use Xi\Filelib\File\FileOperator;
use Xi\Filelib\Command;
use Xi\Filelib\Folder\Command\FolderCommand;
use ArrayIterator;

/**
 * Operates on folders
 * 
 * @package Xi_Filelib
 * @author pekkis
 * 
 */
class DefaultFolderOperator extends AbstractOperator implements FolderOperator
{

    const COMMAND_CREATE = 'create';
        
    /**
     * @var string Folderitem class
     */
    private $className = 'Xi\Filelib\Folder\FolderItem';
    
    protected $commandStrategies = array(
        self::COMMAND_CREATE => Command::STRATEGY_SYNCHRONOUS,
    );
    

    /**
     * Returns directory route for folder
     * 
     * @param Folder $folder 
     * @return string
     */
    public function buildRoute(Folder $folder)
    {
        $rarr = array();

        array_unshift($rarr, $folder->getName());
        $imposter = clone $folder;
        while ($imposter = $this->findParentFolder($imposter)) {

            if ($imposter->getParentId()) {
                array_unshift($rarr, $imposter->getName());
            }
        }

        return implode('/', $rarr);
    }

    /**
     * Sets folderitem class
     *
     * @param string $className Class name
     * @return FolderOperator
     */
    public function setClass($className)
    {
        $this->className = $className;
        return $this;
    }

    /**
     * Returns folderitem class
     *
     * @return string
     */
    public function getClass()
    {
        return $this->className;
    }

    /**
     * Returns an instance of the currently set folder class
     * 
     * @param array $data Data
     */
    public function getInstance(array $data = array())
    {
        $className = $this->getClass();
        $folder = new $className();
        if ($data) {
            $folder->fromArray($data);
        }
        return $folder;
    }

    /**
     * Creates a folder
     *
     * @param Folder $folder
     */
    public function create(Folder $folder)
    {
        $route = $this->buildRoute($folder);
                
        $folder->setUrl($route);

        $folder = $this->getBackend()->createFolder($folder);
        
        return $folder;
    }

    /**
     * Deletes a folder
     *
     * @param Folder $folder Folder
     */
    public function delete(Folder $folder)
    {
        foreach ($this->findSubFolders($folder) as $childFolder) {
            $this->delete($childFolder);
        }

        foreach ($this->findFiles($folder) as $file) {
            $this->getFileOperator()->delete($file);
        }

        $this->getBackend()->deleteFolder($folder);
    }

    /**
     * Updates a folder
     *
     * @param Folder $folder Folder
     */
    public function update(Folder $folder)
    {
        $route = $this->buildRoute($folder);
        $folder->setUrl($route);

        $this->getBackend()->updateFolder($folder);

        foreach ($this->findFiles($folder) as $file) {
            $this->getFileOperator()->update($file);
        }

        foreach ($this->findSubFolders($folder) as $subFolder) {
            $this->update($subFolder);
        }
    }

    /**
     * Finds the root folder
     *
     * @return Folder
     */
    public function findRoot()
    {
        $folder = $this->getBackend()->findRootFolder();

        if (!$folder) {
            throw new FilelibException('Could not locate root folder', 500);
        }

        $folder = $this->getInstance($folder);

        return $folder;
    }

    /**
     * Finds a folder
     *
     * @param mixed $id Folder id
     * @return Folder
     */
    public function find($id)
    {
        $folder = $this->getBackend()->findFolder($id);
        if (!$folder) {
            return false;
        }

        $folder = $this->getInstance($folder);
        return $folder;
    }

    public function findByUrl($url)
    {
        $folder = $this->getBackend()->findFolderByUrl($url);

        if (!$folder) {
            return false;
        }

        $folder = $this->getInstance($folder);
        return $folder;
    }

    public function createByUrl($url)
    {
        $folder = $this->findByUrl($url);
        if ($folder) {
            return $folder;
        }

        $rootFolder = $this->findRoot();

        $exploded = explode('/', $url);

        $folderNames = array();

        $created = null;
        $previous = null;

        $count = 0;

        while (sizeof($exploded) || !$created) {

            $folderNames[] = $folderCurrent = array_shift($exploded);

            $folderName = implode('/', $folderNames);

            $created = $this->findByUrl($folderName);

            if (!$created) {
                $created = $this->getInstance(array(
                    'parent_id' => $previous ? $previous->getId() : $rootFolder->getId(),
                    'name' => $folderCurrent,
                        ));
                $this->create($created);
            }
            $previous = $created;
        }

        return $created;
    }

    /**
     * Finds subfolders
     *
     * @param Folder $folder
     * @return ArrayIterator
     */
    public function findSubFolders(Folder $folder)
    {
        $rawFolders = $this->getBackend()->findSubFolders($folder);

        $folders = array();
        foreach ($rawFolders as $rawFolder) {
            $folder = $this->getInstance($rawFolder);
            $folders[] = $folder;
        }
        return new ArrayIterator($folders);
    }

    /**
     * Finds parent folder
     * 
     * @param Folder $folder
     * @return Folder|false
     */
    public function findParentFolder(Folder $folder)
    {
        if (!$parentId = $folder->getParentId()) {
            return false;
        }

        $parent = $this->getBackend()->findFolder($parentId);

        if (!$parent) {
            return false;
        }

        return $this->getInstance($parent);
    }

    /**
     * @param Folder $folder Folder
     * @return ArrayIterator Collection of file items
     */
    public function findFiles(Folder $folder)
    {
        $ritems = $this->getBackend()->findFilesIn($folder);
                
        $items = array();
        foreach ($ritems as $ritem) {
            $item = $this->getFileOperator()->getInstance($ritem);
            $items[] = $item;
        }

        return new ArrayIterator($items);
    }
    
    
    
    /**
     *
     * @return FileOperator
     */
    public function getFileOperator()
    {
        return $this->getFilelib()->getFileOperator();
    }


}