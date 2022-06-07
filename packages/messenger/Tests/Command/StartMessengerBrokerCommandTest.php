<?php

namespace Draw\Component\Messenger\Tests\Command;

use Draw\Component\Core\Reflection\ReflectionAccessor;
use Draw\Component\Messenger\Command\StartMessengerBrokerCommand;
use Draw\Component\Messenger\Event\BrokerStartedEvent;
use Draw\Component\Tester\Application\CommandDataTester;
use Draw\Component\Tester\Application\CommandTestTrait;
use Draw\Contracts\Process\ProcessFactoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Draw\Component\Messenger\Command\StartMessengerBrokerCommand
 */
class StartMessengerBrokerCommandTest extends TestCase
{
    use CommandTestTrait;

    private ProcessFactoryInterface $processFactory;

    private EventDispatcher $eventDispatcher;

    private string $consolePath;

    public function createCommand(): Command
    {
        return new StartMessengerBrokerCommand(
            $this->consolePath = uniqid('console-path-'),
            $this->processFactory = $this->createMock(ProcessFactoryInterface::class),
            $this->eventDispatcher = new EventDispatcher()
        );
    }

    public function getCommandName(): string
    {
        return 'draw:messenger:start-broker';
    }

    public function provideTestArgument(): iterable
    {
        return [];
    }

    public function provideTestOption(): iterable
    {
        yield [
            'concurrent',
            null,
            InputOption::VALUE_REQUIRED,
            1,
        ];

        yield [
            'timeout',
            null,
            InputOption::VALUE_REQUIRED,
            10,
        ];
    }

    public function testExecuteInvalidConcurrent(): void
    {
        $concurrent = rand(PHP_INT_MIN, 0);
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Concurrent value ['.$concurrent.'] is invalid. Must be 1 or greater');

        $this->execute(['--concurrent' => $concurrent]);
    }

    public function testExecuteInvalidTimeout(): void
    {
        $timeout = rand(PHP_INT_MIN, -1);
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Timeout value ['.$timeout.'] is invalid. Must be 0 or greater');

        $this->execute(['--timeout' => $timeout]);
    }

    public function testExecute(): void
    {
        $concurrent = rand(1, 10);
        $timeout = rand(1, 10);

        $this->eventDispatcher->addListener(
            BrokerStartedEvent::class,
            function (BrokerStartedEvent $event) use ($concurrent, $timeout) {
                $this->assertSame(
                    $concurrent,
                    $event->getConcurrent()
                );

                $this->assertSame(
                    $timeout,
                    $event->getTimeout()
                );

                $broker = $event->getBroker();

                $this->assertSame(
                    $this->processFactory,
                    ReflectionAccessor::getPropertyValue($broker, 'processFactory')
                );

                $this->assertSame(
                    $this->eventDispatcher,
                    ReflectionAccessor::getPropertyValue($broker, 'eventDispatcher')
                );

                $this->assertSame(
                    $this->consolePath,
                    ReflectionAccessor::getPropertyValue($broker, 'consolePath')
                );

                $broker->stop();
            }
        );

        $this->execute(['--concurrent' => $concurrent, '--timeout' => $timeout])
            ->test(
                CommandDataTester::create(
                    0,
                    [
                        '[OK] Broker starting.',
                        '! [NOTE] Concurrency '.$concurrent,
                        '! [NOTE] Timeout '.$timeout,
                        '[OK] Broker stopped. ',
                    ]
                )
            );
    }
}
