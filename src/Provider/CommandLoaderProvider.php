<?php

declare(strict_types=1);

namespace SuperKernel\Command\Provider;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Command\Attribute\Command as AttributeCommand;
use SuperKernel\Command\CommandLoader;
use SuperKernel\Contract\AnnotationCollectorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

use function array_is_list;
use function array_key_exists;
use function is_a;
use function is_string;
use function sprintf;

#[FactoryProvider(CommandLoaderInterface::class)]
final readonly class CommandLoaderProvider
{
    private const string INVALID_ALIASES_MESSAGE = 'Command aliases on %s must be a list of non-empty strings.';

    /** @throws InvalidArgumentException When a command declaration or registration is invalid */
    public function __invoke(
        ContainerInterface           $container,
        AnnotationCollectorInterface $collector,
    ): CommandLoaderInterface
    {
        /** @var array<non-empty-string, class-string<Command>> $commands */
        $commands = [];
        $declarations = [];

        foreach ($collector->getClassesWithAttribute(AttributeCommand::class) as $className => [$metadata]) {
            if (!is_a($className, Command::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    '%s declares %s but does not extend %s.',
                    $className,
                    AttributeCommand::class,
                    Command::class,
                ));
            }

            $declaration = $metadata->attribute();

            if ('' === $declaration->name) {
                throw new InvalidArgumentException(sprintf(
                    '%s on %s must declare a non-empty command name.',
                    AttributeCommand::class,
                    $className,
                ));
            }

            if (!array_is_list($declaration->aliases)) {
                throw new InvalidArgumentException(sprintf(self::INVALID_ALIASES_MESSAGE, $className));
            }

            foreach ($declaration->aliases as $alias) {
                if (!is_string($alias) || '' === $alias) {
                    throw new InvalidArgumentException(sprintf(self::INVALID_ALIASES_MESSAGE, $className));
                }
            }

            /** @var list<non-empty-string> $names */
            $names = [
                $declaration->name,
                ...$declaration->aliases,
            ];

            foreach ($names as $name) {
                if (array_key_exists($name, $commands)) {
                    throw new InvalidArgumentException(sprintf(
                        'Command name "%s" is declared by both %s and %s.',
                        $name,
                        $commands[$name],
                        $className,
                    ));
                }

                $commands[$name] = $className;
            }

            $declarations[$className] = $declaration;
        }

        return new CommandLoader($container, $commands, $declarations);
    }
}
