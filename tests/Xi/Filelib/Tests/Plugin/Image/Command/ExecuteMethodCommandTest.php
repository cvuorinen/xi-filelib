<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xi\Filelib\Tests\Plugin\Image\Command;

use Xi\Filelib\Tests\Plugin\Image\TestCase;
use Xi\Filelib\Plugin\Image\Command\ExecuteMethodCommand;
use Imagick;

/**
 * @group plugin
 */
class ExecuteMethodCommandTest extends TestCase
{
    /**
     * @test
     */
    public function gettersAndSettersShouldWorkAsExpected()
    {
        $command = new ExecuteMethodCommand('lussenhof');

        $this->assertSame('lussenhof', $command->getMethod());
        $this->assertEquals(array(), $command->getParameters());

        $command = new ExecuteMethodCommand('cronsumera', 'mera');
        $this->assertSame('cronsumera', $command->getMethod());
        $this->assertEquals(array('mera'), $command->getParameters());

        $command = new ExecuteMethodCommand('cronsumera', array('mera', 'banana'));
        $this->assertSame('cronsumera', $command->getMethod());
        $this->assertEquals(array('mera', 'banana'), $command->getParameters());

        $command->setMethod('lussutera');
        $command->setParameters(array('grande', 'lusso'));

        $this->assertSame('lussutera', $command->getMethod());
        $this->assertSame(array('grande', 'lusso'), $command->getParameters());
    }

    /**
     * @test
     */
    public function executeShouldFailWhenMethodIsNotCallable()
    {
        $command = new ExecuteMethodCommand('cropThumbnailImagee', array('sometimes', 'a banana'));

        $imagick = $this->getMockedImagick();

        $this->expectException('\BadMethodCallException');

        $command->execute($imagick);
    }

    /**
     * @test
     */
    public function executeShouldExecuteImagemagicksMethodWhenMethodIsCallable()
    {
        $command = new ExecuteMethodCommand('cropThumbnailImage', array(123, 456));

        $imagick = $this->getMockedImagick();

        $imagick->expects($this->once())
                ->method('cropThumbnailImage')
                ->with(123, 456);

        $command->execute($imagick);
    }

}
