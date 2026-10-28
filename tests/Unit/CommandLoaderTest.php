<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use SuperKernel\Command\Attribute\Command as AttributeCommand;
use SuperKernel\Command\CommandLoader;
use SuperKernel\Command\Tests\Fixture\ArrayContainer;
use SuperKernel\Command\Tests\Fixture\RegisteredCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;
use UnexpectedValueException;

final class CommandLoaderTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testExposesNamesAndLazilyResolvesCommandsAndAliases(): void
    {
        $command = new RegisteredCommand('app:registered');
        $container = new ArrayContainer([
            RegisteredCommand::class => $command,
        ]);
        $loader = new CommandLoader($container, [
            'app:registered' => RegisteredCommand::class,
            'app:alias'      => RegisteredCommand::class,
        ], [
            RegisteredCommand::class => new AttributeCommand('app:registered', aliases: [
                'app:alias',
            ]),
        ]);

        self::assertTrue($loader->has('app:registered'));
        self::assertTrue($loader->has('app:alias'));
        self::assertFalse($loader->has('app:missing'));
        self::assertSame([
            'app:registered',
            'app:alias',
        ], $loader->getNames());
        self::assertSame([], $container->requested);
        $lazy = $loader->get('app:alias');
        self::assertInstanceOf(LazyCommand::class, $lazy);
        self::assertSame([], $container->requested);
        self::assertSame($command, $lazy->getCommand());
        self::assertSame([
            RegisteredCommand::class,
        ], $container->requested);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRunsARegisteredCommandThroughSymfonyConsole(): void
    {
        $command = new RegisteredCommand('app:registered');
        $container = new ArrayContainer([
            RegisteredCommand::class => $command,
        ]);
        $loader = new CommandLoader($container, [
            'app:registered' => RegisteredCommand::class,
            'app:alias'      => RegisteredCommand::class,
        ], [
            RegisteredCommand::class => new AttributeCommand('app:registered', aliases: [
                'app:alias',
            ]),
        ]);
        $application = new Application();
        $output = new BufferedOutput();
        $application->setAutoExit(false);
        $application->setCommandLoader($loader);

        $status = $application->run(
            new ArrayInput([
                'command' => 'app:registered',
            ]),
            $output,
        );

        self::assertSame(0, $status);
        self::assertSame("registered\n", $output->fetch());
        self::assertSame([
            RegisteredCommand::class,
        ], $container->requested);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAnUnknownCommandName(): void
    {
        $loader = new CommandLoader(new ArrayContainer([]), [], []);

        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessageIsOrContains('Command "app:missing" does not exist.');

        $loader->get('app:missing');
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAnIncompatibleContainerEntry(): void
    {
        $loader = new CommandLoader(
            new ArrayContainer([
                RegisteredCommand::class => new stdClass(),
            ]),
            [
                'app:registered' => RegisteredCommand::class,
            ],
            [
                RegisteredCommand::class => new AttributeCommand('app:registered'),
            ],
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('must resolve to');

        $loader->get('app:registered')->getDefinition();
    }
    /** @throws Throwable When fixture registration fails */
    public function testMissingDeclarationNeverResolvesTheCommand(): void
    {
        $container = new ArrayContainer([]);
        $loader = new CommandLoader($container, [
            'app:registered' => RegisteredCommand::class,
        ], []);
        try {
            $loader->get('app:registered');
            self::fail('Missing declarations must be rejected.');
        } catch (UnexpectedValueException $exception) {
            self::assertStringContainsString('requires a Command declaration', $exception->getMessage());
            self::assertSame([], $container->requested);
        }
    }

}
