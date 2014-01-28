<?php

namespace Xi\Filelib\Tests\File\Command;

use Xi\Filelib\FileLibrary;
use Xi\Filelib\File\FileOperator;
use Xi\Filelib\File\File;
use Xi\Filelib\File\Command\UpdateFileCommand;
use Xi\Filelib\Events;
use Xi\Filelib\File\Resource;

class UpdateFileCommandTest extends \Xi\Filelib\Tests\TestCase
{

    /**
     * @test
     */
    public function classShouldExist()
    {
        $this->assertTrue(class_exists('Xi\Filelib\File\Command\UpdateFileCommand'));
        $this->assertContains('Xi\Filelib\File\Command\FileCommand', class_implements('Xi\Filelib\File\Command\UpdateFileCommand'));
    }

    /**
     * @test
     */
    public function updateShouldDelegateCorrectly()
    {
        $filelib = $this->getMockedFilelib();
        $ed = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $filelib->expects($this->any())->method('getEventDispatcher')->will($this->returnValue($ed));

        $ed
            ->expects($this->at(0))
            ->method('dispatch')
            ->with(
                $this->equalTo(Events::FILE_BEFORE_UPDATE),
                $this->isInstanceOf('Xi\Filelib\Event\FileEvent')
            );

        $ed
            ->expects($this->at(1))
            ->method('dispatch')
            ->with(
            $this->equalTo(Events::FILE_AFTER_UPDATE),
            $this->isInstanceOf('Xi\Filelib\Event\FileEvent')
        );

        $op = $this->getMockBuilder('Xi\Filelib\File\FileOperator')
                   ->setConstructorArgs(array($filelib))
                   ->setMethods(array('getProfile', 'createCommand'))
                   ->getMock();

        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $filelib->expects($this->any())->method('getEventDispatcher')->will($this->returnValue($dispatcher));

        $profile = $this->getMockedFileProfile();

        $file = $this->getMockedFile();
        $file->setProfile('lussenhofer');

        $backend = $this
            ->getMockBuilder('Xi\Filelib\Backend\Backend')
            ->disableOriginalConstructor()
            ->getMock();

        $backend->expects($this->once())->method('updateFile')->with($this->equalTo($file));

        $filelib->expects($this->any())->method('getBackend')->will($this->returnValue($backend));

        $op->expects($this->any())->method('getProfile')->with($this->equalTo('lussenhofer'))->will($this->returnValue($profile));

        $command = new UpdateFileCommand( $file);
        $command->attachTo($this->getMockedFilelib(null, $op));
        $command->execute();
    }

    /**
     * @test
     */
    public function returnsProperMessage()
    {
        $file = File::create(array('id' => 321));

        $command = new UpdateFileCommand($file);

        $message = $command->getMessage();

        $this->assertInstanceOf('Pekkis\Queue\Message', $message);
        $this->assertSame('xi_filelib.command.file.update', $message->getType());

        $darr = $file->toArray();
        $darr['resource_id'] = null;
        unset($darr['resource']);

        $this->assertEquals(
            array(
                'file_data' => $darr,
            ),
            $message->getData()
        );
    }

    /**
     * @test
     */
    public function returnsProperMessageWithResource()
    {
        $file = File::create(
            array(
                'id' => 321,
                'resource' => Resource::create(array('id' => 986))
            )
        );

        $command = new UpdateFileCommand($file);

        $message = $command->getMessage();

        $this->assertInstanceOf('Pekkis\Queue\Message', $message);
        $this->assertSame('xi_filelib.command.file.update', $message->getType());

        $darr = $file->toArray();
        $darr['resource_id'] = 986;
        unset($darr['resource']);

        $this->assertEquals(
            array(
                'file_data' => $darr,
            ),
            $message->getData()
        );
    }

}
