<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Plugin\Image;

use Xi\Filelib\Plugin\AbstractPlugin;
use Xi\Filelib\Event\FileUploadEvent;
use Xi\Filelib\File\FileRepository;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Events;
use Xi\Filelib\File\Upload\FileUpload;

/**
 * Changes images' formats before uploading them.
 *
 * @author pekkis
 */
class ChangeFormatPlugin extends AbstractPlugin
{
    protected static $subscribedEvents = array(
        Events::PROFILE_AFTER_ADD => 'onFileProfileAdd',
        Events::FILE_BEFORE_CREATE => 'beforeUpload'
    );

    protected $imageMagickHelper;

    protected $targetExtension;

    /**
     * @var FileRepository
     */
    private $fileRepository;

    /**
     * @var string
     */
    private $tempDir;

    /**
     * @param  FileRepository       $fileRepository
     * @param  string             $tempDir
     * @param  array              $options
     * @return ChangeFormatPlugin
     */
    public function __construct($targetExtension, array $commandDefinitions = array())
    {
        $this->targetExtension = $targetExtension;
        $this->imageMagickHelper = new ImageMagickHelper($commandDefinitions);
    }

    /**
     * Returns ImageMagick helper
     *
     * @return ImageMagickHelper
     */
    public function getImageMagickHelper()
    {
        return $this->imageMagickHelper;
    }

    /**
     * Returns target file extension
     *
     * @return string
     */
    public function getTargetExtension()
    {
        return $this->targetExtension;
    }

    public function beforeUpload(FileUploadEvent $event)
    {
        if (!$this->hasProfile($event->getProfile()->getIdentifier())) {
            return;
        }

        $upload = $event->getFileUpload();

        $mimetype = $upload->getMimeType();
        if (!preg_match("/^image/", $mimetype)) {
            return;
        }

        $img = $this->getImageMagickHelper()->createImagick($upload->getRealPath());
        $this->getImageMagickHelper()->execute($img);


        $tempnam = $this->tempDir . '/' . uniqid('cfp', true);
        $img->writeImage($tempnam);

        $pinfo = pathinfo($upload->getUploadFilename());

        $nupload = new FileUpload($tempnam);
        $nupload->setTemporary(true);

        $nupload->setOverrideFilename($pinfo['filename'] . '.' . $this->getTargetExtension());

        $event->setFileUpload($nupload);

        return $nupload;
    }

    /**
     * @param FileLibrary $filelib
     */
    public function attachTo(FileLibrary $filelib)
    {
        $this->fileRepository = $filelib->getFileRepository();
        $this->tempDir = $filelib->getTempDir();
    }
}
