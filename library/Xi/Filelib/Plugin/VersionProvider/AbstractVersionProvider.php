<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Plugin\VersionProvider;

use Xi\Filelib\File\File;
use Xi\Filelib\File\FileOperator;
use Xi\Filelib\FilelibException;
use Xi\Filelib\Plugin\AbstractPlugin;
use Xi\Filelib\Plugin\VersionProvider\VersionProvider;
use Xi\Filelib\Event\FileEvent;
use Xi\Filelib\Storage\Storage;
use Xi\Filelib\Publisher\Publisher;

/**
 * Abstract convenience class for version provider plugins
 *
 * @author pekkis
 */
abstract class AbstractVersionProvider extends AbstractPlugin implements VersionProvider
{
    protected static $subscribedEvents = array(
        'fileprofile.add' => 'onFileProfileAdd',
        'file.afterUpload' => 'afterUpload',
        'file.publish' => 'onPublish',
        'file.unpublish' => 'onUnpublish',
        'file.delete' => 'onDelete',
    );

    /**
     * @var string Version identifier
     */
    protected $identifier;

    /**
     * @var array Array of file types for which the plugin provides a version
     */
    protected $providesFor = array();

    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var Publisher
     */
    private $publisher;

    /**
     * @var FileOperator
     */
    private $fileOperator;

    /**
     * @param  Storage                 $storage
     * @param  Publisher               $publisher
     * @param  FileOperator            $fileOperator
     * @param  array                   $options
     * @return AbstractVersionProvider
     */
    public function __construct(Storage $storage, Publisher $publisher,
        FileOperator $fileOperator, array $options = array()
    ) {
        parent::__construct($options);

        $this->storage = $storage;
        $this->publisher = $publisher;
        $this->fileOperator = $fileOperator;
    }

    abstract public function createVersions(File $file);

    /**
     * Registers a version to all profiles
     */
    public function init()
    {
        if (!$this->getIdentifier()) {
            throw new FilelibException('Version plugin must have an identifier');
        }

        foreach ($this->getProvidesFor() as $fileType) {
            foreach ($this->getProfiles() as $profile) {
                $profile = $this->fileOperator->getProfile($profile);

                foreach ($this->getVersions() as $version) {
                    $profile->addFileVersion($fileType, $version, $this);
                }
            }
        }
    }

    /**
     * Sets identifier
     *
     * @param  string          $identifier
     * @return VersionProvider
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Returns identifier
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Sets file types for this version plugin.
     *
     * @param  array           $providesFor Array of file types
     * @return VersionProvider
     */
    public function setProvidesFor(array $providesFor)
    {
        $this->providesFor = $providesFor;

        return $this;
    }

    /**
     * Returns file types which the version plugin provides version for.
     *
     * @return array
     */
    public function getProvidesFor()
    {
        return $this->providesFor;
    }

    /**
     * Returns whether the plugin provides a version for a file.
     *
     * @param  File    $file File item
     * @return boolean
     */
    public function providesFor(File $file)
    {
        if (in_array($this->fileOperator->getType($file), $this->getProvidesFor())) {
            if (in_array($file->getProfile(), $this->getProfiles())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns storage
     *
     * @return Storage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Returns publisher
     *
     * @return Publisher
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    public function afterUpload(FileEvent $event)
    {
        $file = $event->getFile();

        if (!$this->hasProfile($file->getProfile())) {
            return;
        }

        if (!$this->providesFor($file)) {
            return;
        }

        $tmps = $this->createVersions($file);
        foreach ($tmps as $version => $tmp) {
            $this->getStorage()->storeVersion($file, $version, $tmp);
            unlink($tmp);
        }
    }

    public function onPublish(FileEvent $event)
    {
        $file = $event->getFile();

        if (!$this->hasProfile($file->getProfile())) {
            return;
        }

        if (!$this->providesFor($file)) {
            return;
        }

        foreach ($this->getVersions() as $version) {
            $this->getPublisher()->publishVersion($file, $version, $this);
        }
    }

    public function onUnpublish(FileEvent $event)
    {
        $file = $event->getFile();

        if (!$this->hasProfile($file->getProfile())) {
            return;
        }

        if (!$this->providesFor($file)) {
            return;
        }

        foreach ($this->getVersions() as $version) {
            $this->getPublisher()->unpublishVersion($file, $version, $this);
        }
    }

    public function onDelete(FileEvent $event)
    {
        $file = $event->getFile();

        if (!$this->hasProfile($file->getProfile())) {
            return;
        }

        if (!$this->providesFor($file)) {
            return;
        }

        $this->deleteVersions($file);
    }

    /**
     * Deletes a version
     *
     * @param File $file
     */
    public function deleteVersions(File $file)
    {
        foreach ($this->getVersions() as $version) {
            $this->getStorage()->deleteVersion($file, $version);
        }
    }
}
