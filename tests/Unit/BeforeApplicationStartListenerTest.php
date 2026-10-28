<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SuperKernel\Attribute\Listener;
use SuperKernel\Command\Attribute\Command as AttributeCommand;
use SuperKernel\Command\CommandLoader;
use SuperKernel\Command\Listener\BeforeApplicationStartListener;
use SuperKernel\Command\Tests\Fixture\ArrayContainer;
use SuperKernel\Command\Tests\Fixture\RegisteredCommand;
use SuperKernel\Contract\ApplicationInterface;
use SuperKernel\Event\BeforeApplicationStart;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class BeforeApplicationStartListenerTest extends TestCase
{
    public function testDeclaresTheStartupEventAndDependsOnlyOnTheCommandLoader(): void
    {
        $reflection = new ReflectionClass(BeforeApplicationStartListener::class);
        $listener = new BeforeApplicationStartListener($this->createStub(CommandLoaderInterface::class));

        self::assertCount(1, $reflection->getAttributes(Listener::class));
        self::assertSame([
            BeforeApplicationStart::class,
        ], $listener->listen());
        self::assertCount(1, $reflection->getConstructor()->getParameters());
        self::assertSame(
            CommandLoaderInterface::class,
            (string) $reflection->getConstructor()->getParameters()[0]->getType(),
        );
    }

    public function testAttachesTheLoaderToEveryApplicationWithoutResolvingCommands(): void
    {
        $resolutions = 0;
        $loader = new FactoryCommandLoader([
            'app:registered' => static function () use (&$resolutions): Command {
                ++$resolutions;

                return new RegisteredCommand('app:registered');
            },
        ]);
        $listener = new BeforeApplicationStartListener($loader);

        for ($index = 0; 2 > $index; ++$index) {
            $application = new class() extends Application implements ApplicationInterface {};

            self::assertFalse($application->has('app:registered'));
            $listener->process(new BeforeApplicationStart($application));
            self::assertSame($index, $resolutions);
            self::assertTrue($application->has('app:registered'));
        }

        self::assertSame(2, $resolutions);
    }

    /**
     * @throws Exception if Console command initialization or execution fails
     */
    public function testPreservesLazyListingAndAliasExecution(): void
    {
        $command = new RegisteredCommand();
        $container = new ArrayContainer([
            RegisteredCommand::class => $command,
        ]);
        $declaration = new ReflectionClass(RegisteredCommand::class)
            ->getAttributes(AttributeCommand::class)[0]
            ->newInstance();
        $loader = new CommandLoader($container, [
            'app:registered' => RegisteredCommand::class,
            'app:alias'      => RegisteredCommand::class,
        ], [
            RegisteredCommand::class => $declaration,
        ]);
        $application = new class() extends Application implements ApplicationInterface {};
        $application->setAutoExit(false);
        $listener = new BeforeApplicationStartListener($loader);
        $listener->process(new BeforeApplicationStart($application));

        $output = new BufferedOutput();
        $status = $application->run(new ArgvInput([
            'console',
            'list',
            '--raw',
        ]), $output);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('app:registered', $output->fetch());
        self::assertSame([], $container->requested);

        $output = new BufferedOutput();
        $status = $application->run(new ArgvInput([
            'console',
            'app:alias',
        ]), $output);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame("registered\n", $output->fetch());
        self::assertSame([
            RegisteredCommand::class,
        ], $container->requested);
    }

    public function testIgnoresApplicationsWithoutSymfonyConsoleSupport(): void
    {
        $application = $this->createMock(ApplicationInterface::class);
        $application->expects(self::never())->method('run');
        $loader = $this->createMock(CommandLoaderInterface::class);
        $loader->expects(self::never())->method('get');
        $loader->expects(self::never())->method('has');
        $loader->expects(self::never())->method('getNames');

        new BeforeApplicationStartListener($loader)->process(new BeforeApplicationStart($application));
    }
}
