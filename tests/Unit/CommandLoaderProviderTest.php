<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use stdClass;
use SuperKernel\Annotator\AnnotationCollector;
use SuperKernel\Annotator\Model\AttributeMetadata;
use SuperKernel\Annotator\Model\PackageAnnotations;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Command\Attribute\Command;
use SuperKernel\Command\Provider\CommandLoaderProvider;
use SuperKernel\Command\Tests\Fixture\AnnotatedCommand;
use SuperKernel\Command\Tests\Fixture\ArrayContainer;
use SuperKernel\Command\Tests\Fixture\RegisteredCommand;
use SuperKernel\Command\Tests\Fixture\SecondaryCommand;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\AttributeMetadataInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;
use UnexpectedValueException;

use function class_exists;
use function spl_autoload_register;
use function spl_autoload_unregister;

final class CommandLoaderProviderTest extends TestCase
{
    /** @throws Throwable When reflecting or executing the framework-only command fails */
    public function testRunsAFrameworkOnlyDeclarationWithoutSymfonyAttributeMetadata(): void
    {
        $declaration = new ReflectionClass(AnnotatedCommand::class)->getAttributes(Command::class)[0]->newInstance();
        $collector = $this->collector([
            AnnotatedCommand::class => [
                new AttributeMetadata($declaration),
            ],
        ]);
        $command = new AnnotatedCommand();
        self::assertNull($command->getName());
        $container = new ArrayContainer([
            AnnotatedCommand::class => $command,
        ]);
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader(new CommandLoaderProvider()($container, $collector));
        $output = new BufferedOutput();
        self::assertSame(0, $application->run(new ArrayInput([
            'command' => 'annotated',
        ]), $output));
        self::assertSame("annotated\n", $output->fetch());
        self::assertSame([
            AnnotatedCommand::class,
        ], $container->requested);
        self::assertSame('app:annotated', $command->getName());
        self::assertSame('A framework command.', $command->getDescription());
    }

    public function testAcceptsIndependentMetadataWhileKeepingCommandResolutionLazy(): void
    {
        $command = new RegisteredCommand();
        $metadata = $this->createStub(AttributeMetadataInterface::class);
        $metadata->method('attribute')->willReturn(new Command('app:custom', aliases: [
            'app:custom-alias',
        ]));
        $collector = $this->createMock(AnnotationCollectorInterface::class);
        $collector->expects($this->never())->method('getClassesWithAttributeAssignableTo');
        $collector->expects($this->once())->method('getClassesWithAttribute')->with(Command::class)->willReturn([
            RegisteredCommand::class => [
                $metadata,
            ],
        ]);
        $container = new ArrayContainer([
            RegisteredCommand::class => $command,
        ]);

        $loader = new CommandLoaderProvider()($container, $collector);

        self::assertSame([
            'app:custom',
            'app:custom-alias',
        ], $loader->getNames());
        self::assertSame([], $container->requested);
        $lazy = $loader->get('app:custom-alias');
        self::assertInstanceOf(LazyCommand::class, $lazy);
        self::assertSame($command, $lazy->getCommand());
        self::assertSame([
            RegisteredCommand::class,
        ], $container->requested);
    }

    public function testDeclaresTheSharedFactoryProviderContract(): void
    {
        $provider = new ReflectionClass(CommandLoaderProvider::class);
        $attributes = $provider->getAttributes(FactoryProvider::class);
        $invoke = $provider->getMethod('__invoke');

        self::assertTrue($provider->isFinal());
        self::assertTrue($provider->isReadOnly());
        self::assertCount(1, $attributes);
        self::assertSame(CommandLoaderInterface::class, $attributes[0]->newInstance()->className);
        self::assertNull($provider->getConstructor());
        self::assertTrue($invoke->isPublic());
        self::assertFalse($invoke->isStatic());
        self::assertSame(CommandLoaderProvider::class, $invoke->getDeclaringClass()->getName());
        self::assertSame(2, $invoke->getNumberOfRequiredParameters());
        self::assertCount(2, $invoke->getParameters());
        self::assertSame(
            ContainerInterface::class,
            (string) $invoke->getParameters()[0]->getType(),
        );
        self::assertSame(
            AnnotationCollectorInterface::class,
            (string) $invoke->getParameters()[1]->getType(),
        );
        self::assertSame(CommandLoaderInterface::class, (string) $invoke->getReturnType());
    }

    public function testDiscoversNamesAndAliasesWithoutResolvingCommandsEagerly(): void
    {
        $command = new RegisteredCommand();
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(
                    name: 'app:registered',
                    aliases: [
                        'app:alias',
                    ],
                )),
            ],
        ]);
        $container = new ArrayContainer([
            RegisteredCommand::class => $command,
        ]);

        $loader = new CommandLoaderProvider()($container, $collector);

        self::assertSame([
            'app:registered',
            'app:alias',
        ], $loader->getNames());
        self::assertSame([], $container->requested);
        $lazy = $loader->get('app:alias');

        self::assertInstanceOf(LazyCommand::class, $lazy);
        self::assertSame('app:registered', $lazy->getName());
        self::assertSame([
            'app:alias',
        ], $lazy->getAliases());
        self::assertSame([], $container->requested);
        self::assertSame($command, $lazy->getCommand());
        self::assertSame([
            RegisteredCommand::class,
        ], $container->requested);
    }

    public function testUsesTheSuppliedDependenciesForEachInvocation(): void
    {
        $firstCommand = new RegisteredCommand();
        $secondCommand = new SecondaryCommand();
        $firstContainer = new ArrayContainer([
            RegisteredCommand::class => $firstCommand,
        ]);
        $secondContainer = new ArrayContainer([
            SecondaryCommand::class => $secondCommand,
        ]);
        $firstCollector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(name: 'app:registered')),
            ],
        ]);
        $secondCollector = $this->collector([
            SecondaryCommand::class => [
                new AttributeMetadata(new Command(name: 'app:secondary')),
            ],
        ]);
        $provider = new CommandLoaderProvider();

        $first = $provider($firstContainer, $firstCollector);
        $second = $provider($secondContainer, $secondCollector);

        self::assertNotSame($first, $second);
        self::assertSame([
            'app:registered',
        ], $first->getNames());
        self::assertSame([
            'app:secondary',
        ], $second->getNames());
        $firstLazy = $first->get('app:registered');
        $secondLazy = $second->get('app:secondary');
        self::assertInstanceOf(LazyCommand::class, $firstLazy);
        self::assertInstanceOf(LazyCommand::class, $secondLazy);
        self::assertSame([], $firstContainer->requested);
        self::assertSame([], $secondContainer->requested);
        self::assertSame($firstCommand, $firstLazy->getCommand());
        self::assertSame($secondCommand, $secondLazy->getCommand());
        self::assertSame([
            RegisteredCommand::class,
        ], $firstContainer->requested);
        self::assertSame([
            SecondaryCommand::class,
        ], $secondContainer->requested);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testListsCommandsWithoutResolvingTheirDependencies(): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(
                    name: 'app:registered',
                    description: 'A command with unavailable dependencies.',
                )),
            ],
        ]);
        $container = new ArrayContainer([]);
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader(new CommandLoaderProvider()($container, $collector));
        $output = new BufferedOutput();

        $status = $application->run(new ArrayInput([
            'command' => 'list',
        ]), $output);

        self::assertSame(0, $status);
        self::assertStringContainsString('A command with unavailable dependencies.', $output->fetch());
        self::assertSame([], $container->requested);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testUnknownCommandsDoNotResolveRegisteredCommandsForSuggestions(): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(name: 'app:registered')),
            ],
        ]);
        $container = new ArrayContainer([]);
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader(new CommandLoaderProvider()($container, $collector));
        $output = new BufferedOutput();

        $status = $application->run(new ArrayInput([
            'command' => 'unregistered-command',
        ]), $output);

        self::assertSame(1, $status);
        self::assertStringContainsString('is not defined', $output->fetch());
        self::assertSame([], $container->requested);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRunsALazyCommandByItsAliasAndPreservesHiddenMetadata(): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(
                    name: 'app:registered',
                    description: 'A hidden command.',
                    aliases: [
                        'app:alias',
                    ],
                    hidden: true,
                )),
            ],
        ]);
        $container = new ArrayContainer([
            RegisteredCommand::class => new RegisteredCommand(),
        ]);
        $loader = new CommandLoaderProvider()($container, $collector);
        $lazy = $loader->get('app:alias');

        self::assertTrue($lazy->isHidden());
        self::assertSame('A hidden command.', $lazy->getDescription());
        self::assertSame([], $container->requested);

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader($loader);
        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput([
            'command' => 'app:alias',
        ]), $output);

        self::assertSame(0, $status);
        self::assertSame("registered\n", $output->fetch());
        self::assertSame([
            RegisteredCommand::class,
        ], $container->requested);
    }

    public function testLazyResolutionStillRejectsAnIncompatibleContainerEntry(): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(name: 'app:registered')),
            ],
        ]);
        $container = new ArrayContainer([
            RegisteredCommand::class => new stdClass(),
        ]);
        $lazy = new CommandLoaderProvider()($container, $collector)->get('app:registered');

        self::assertInstanceOf(LazyCommand::class, $lazy);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('must resolve to');

        $lazy->getCommand();
    }

    public function testRejectsADeclarationOnANonCommandClass(): void
    {
        $collector = $this->collector([
            stdClass::class => [
                new AttributeMetadata(new Command(name: 'app:invalid')),
            ],
        ]);
        $container = new ArrayContainer([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not extend');

        new CommandLoaderProvider()($container, $collector);
    }

    public function testRejectsAnEmptyCommandName(): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(name: '')),
            ],
        ]);
        $container = new ArrayContainer([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must declare a non-empty command name');

        new CommandLoaderProvider()($container, $collector);
    }

    public function testRejectsDuplicateCommandNamesAndAliases(): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command(name: 'app:registered')),
            ],
            SecondaryCommand::class => [
                new AttributeMetadata(new Command(
                    name: 'app:secondary',
                    aliases: [
                        'app:registered',
                    ],
                )),
            ],
        ]);
        $container = new ArrayContainer([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('is declared by both');

        new CommandLoaderProvider()($container, $collector);
    }

    public function testOnlyTheExactFrameworkDeclarationRegistersCommandsRegardlessOfPackageName(): void
    {
        $collector = new AnnotationCollector([
            new PackageAnnotations('acme/plugin', null, [
                RegisteredCommand::class => [
                    new AttributeMetadata(new Command('plugin:registered')),
                ],
            ], [], []),
            new PackageAnnotations('acme/application', null, [
                SecondaryCommand::class => [
                    new AttributeMetadata(new AsCommand('legacy:ignored')),
                ],
            ], [], []),
        ]);
        $loader = new CommandLoaderProvider()(new ArrayContainer([]), $collector);
        self::assertSame([
            'plugin:registered',
        ], $loader->getNames());
        self::assertFalse($loader->has('legacy:ignored'));
    }

    public function testSymfonyOnlyDeclarationsDoNotLoadTheirTargets(): void
    {
        $class = 'UnloadedCommandFixture\\SymfonyOnly';
        $loaded = [];
        $autoload = static function (string $name) use (&$loaded): void {
            $loaded[] = $name;
        };
        $collector = $this->collector([
            $class => [
                new AttributeMetadata(new AsCommand('legacy:ignored')),
            ],
        ]);
        spl_autoload_register($autoload);
        try {
            self::assertFalse(class_exists($class, false));
            $loader = new CommandLoaderProvider()(new ArrayContainer([]), $collector);
            self::assertSame([], $loader->getNames());
            self::assertFalse(class_exists($class, false));
            self::assertNotContains($class, $loaded);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    /** @return iterable<string, array{string, array<array-key, mixed>}> */
    public static function invalidDeclarations(): iterable
    {
        yield 'empty name with an alias' => [
            '',
            [
                'alias',
            ],
        ];
        yield 'empty alias' => [
            'app:name',
            [
                '',
            ],
        ];
        yield 'non-string alias' => [
            'app:name',
            [
                1,
            ],
        ];
        yield 'not a list' => [
            'app:name',
            [
                'named' => 'alias',
            ],
        ];
        yield 'repeated alias' => [
            'app:name',
            [
                'alias',
                'alias',
            ],
        ];
        yield 'alias repeats primary' => [
            'app:name',
            [
                'app:name',
            ],
        ];
    }

    /** @param array<array-key, mixed> $aliases */
    #[DataProvider('invalidDeclarations')]
    public function testRejectsInvalidNamesAndAliasLists(string $name, array $aliases): void
    {
        $collector = $this->collector([
            RegisteredCommand::class => [
                new AttributeMetadata(new Command($name, aliases: $aliases)),
            ],
        ]);
        $this->expectException(InvalidArgumentException::class);
        new CommandLoaderProvider()(new ArrayContainer([]), $collector);
    }

    /**
     * @param array<class-string, list<AttributeMetadata<object>>> $classAttributes
     */
    private function collector(array $classAttributes): AnnotationCollectorInterface
    {
        return new AnnotationCollector([
            new PackageAnnotations(
                'super-kernel/command-tests',
                null,
                $classAttributes,
                [],
                [],
            ),
        ]);
    }
}
