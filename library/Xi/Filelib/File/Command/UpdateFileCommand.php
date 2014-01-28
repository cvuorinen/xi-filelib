<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\File\Command;

use Xi\Filelib\File\FileOperator;
use Xi\Filelib\File\File;
use Xi\Filelib\Event\FileEvent;
use Xi\Filelib\Events;
use Pekkis\Queue\Message;

class UpdateFileCommand extends AbstractFileCommand
{
    /**
     *
     * @var File
     */
    private $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function execute()
    {
        $event = new FileEvent($this->file);
        $this->fileOperator->getEventDispatcher()->dispatch(Events::FILE_BEFORE_UPDATE, $event);

        $this->fileOperator->getBackend()->updateFile($this->file);

        $event = new FileEvent($this->file);
        $this->fileOperator->getEventDispatcher()->dispatch(Events::FILE_AFTER_UPDATE, $event);

        return $this->file;
    }

    /**
     * @return Message
     */
    public function getMessage()
    {
        $darr = $this->file->toArray();
        unset($darr['resource']);
        $darr['resource_id'] = $this->file->getResource() ? $this->file->getResource()->getId() : null;

        return Message::create(
            'xi_filelib.command.file.update',
            array(
                'file_data' => $darr,
            )
        );
    }
}
