<?php

namespace Oro\Component\MessageQueue\Tests\Unit\Client;

use Oro\Component\MessageQueue\Client\ConsumeMessagesCommand;
use Oro\Component\MessageQueue\Client\Meta\DestinationMeta;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use Oro\Component\MessageQueue\Consumption\ChainExtension;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Consumption\QueueConsumer;
use Oro\Component\MessageQueue\Transport\ConnectionInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ConsumeMessagesCommandTest extends \PHPUnit\Framework\TestCase
{
    /** @var ConsumeMessagesCommand */
    private $command;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $consumer;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $registry;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $processor;

    protected function setUp(): void
    {
        $this->consumer = $this->createMock(QueueConsumer::class);
        $this->registry = $this->createMock(DestinationMetaRegistry::class);
        $this->processor = $this->createMock(MessageProcessorInterface::class);

        $this->command = new ConsumeMessagesCommand($this->consumer, $this->registry, $this->processor);
    }

    public function testShouldHaveCommandName()
    {
        $this->assertEquals('oro:message-queue:consume', $this->command->getName());
    }

    public function testShouldHaveExpectedOptions()
    {
        $options = $this->command->getDefinition()->getOptions();

        $this->assertCount(3, $options);
        $this->assertArrayHasKey('memory-limit', $options);
        $this->assertArrayHasKey('message-limit', $options);
        $this->assertArrayHasKey('time-limit', $options);
    }

    public function testShouldHaveExpectedAttributes()
    {
        $arguments = $this->command->getDefinition()->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('clientDestinationName', $arguments);
    }

    public function testShouldExecuteConsumptionAndUseDefaultQueueName()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('close');

        $this->consumer->expects($this->once())
            ->method('bind')
            ->with('aprefixt.adefaultqueuename', $this->identicalTo($this->processor));
        $this->consumer->expects($this->once())
            ->method('consume')
            ->with($this->isInstanceOf(ChainExtension::class));
        $this->consumer->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $this->registry->expects($this->once())
            ->method('getDestinationsMeta')
            ->willReturn([
                new DestinationMeta('aclient', 'aprefixt.adefaultqueuename')
            ]);

        $tester = new CommandTester($this->command);
        $tester->execute([]);
    }

    public function testShouldExecuteConsumptionAndUseCustomClientDestinationName()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('close');

        $this->consumer->expects($this->once())
            ->method('bind')
            ->with('aprefixt.non-default-queue', $this->identicalTo($this->processor));
        $this->consumer->expects($this->once())
            ->method('consume')
            ->with($this->isInstanceOf(ChainExtension::class));
        $this->consumer->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $this->registry->expects($this->once())
            ->method('getDestinationMeta')
            ->with('non-default-queue')
            ->willReturn(new DestinationMeta('aclient', 'aprefixt.non-default-queue'));

        $tester = new CommandTester($this->command);
        $tester->execute([
            'clientDestinationName' => 'non-default-queue'
        ]);
    }

    public function testShouldExecuteConsumptionAndUseCustomClientDestinationNameWithCustomQueueFromArgument()
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('close');

        $this->consumer->expects($this->once())
            ->method('bind')
            ->with('non-default-transport-queue', $this->identicalTo($this->processor));
        $this->consumer->expects($this->once())
            ->method('consume')
            ->with($this->isInstanceOf(ChainExtension::class));
        $this->consumer->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $this->registry->expects($this->once())
            ->method('getDestinationMeta')
            ->with('non-default-queue')
            ->willReturn(new DestinationMeta('aclient', 'non-default-transport-queue'));

        $tester = new CommandTester($this->command);
        $tester->execute([
            'clientDestinationName' => 'non-default-queue'
        ]);
    }

    public function testShouldLogErrorAndThrowExceptionIfConsumeThrowsException()
    {
        $expectedException = new \Exception('the message');

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('close');

        $this->consumer->expects($this->once())
            ->method('bind')
            ->with('aprefixt.adefaultqueuename', $this->identicalTo($this->processor));
        $this->consumer->expects($this->once())
            ->method('consume')
            ->with($this->isInstanceOf(ChainExtension::class))
            ->willThrowException($expectedException);
        $this->consumer->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $this->registry->expects($this->once())
            ->method('getDestinationsMeta')
            ->willReturn([
                new DestinationMeta('aclient', 'aprefixt.adefaultqueuename')
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectedException->getMessage());

        $tester = new CommandTester($this->command);
        $tester->execute([]);
    }
}
