<?php

declare(strict_types=1);

namespace SuperKernel\Command;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use SuperKernel\Command\Attribute\Command as AttributeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use UnexpectedValueException;

use function array_key_exists;
use function array_keys;
use function array_shift;
use function get_debug_type;
use function sprintf;

final readonly class CommandLoader implements CommandLoaderInterface
{
    /**
     * @param array<non-empty-string, class-string<Command>> $commands
     * @param array<class-string<Command>, AttributeCommand> $declarations Validated command metadata for lazy listing
     */
    public function __construct(
        private ContainerInterface $container,
        private array              $commands,
        private array              $declarations,
    )
    {
    }

    /**
     * @throws CommandNotFoundException When the command name is not registered
     * @throws UnexpectedValueException When the mapped command has no declaration
     */
    public function get(string $name): Command
    {
        if (!array_key_exists($name, $this->commands)) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        $className = $this->commands[$name];
        $declaration = $this->declarations[$className] ?? null;

        if (!$declaration instanceof AttributeCommand) {
            throw new UnexpectedValueException(sprintf('Command %s requires a Command declaration.', $className));
        }

        $names = array_keys($this->commands, $className, true);

        return new LazyCommand(
            array_shift($names),
            $names,
            $declaration->description ?? '',
            $declaration->hidden,
            fn(): Command => $this->resolve($className),
        );
    }

    /**
     * @psalm-param class-string<Command> $className
     *
     * @throws ContainerExceptionInterface When the command entry is missing or cannot be resolved
     * @throws UnexpectedValueException    When the resolved entry is not a Command
     */
    private function resolve(string $className): Command
    {
        $command = $this->container->get($className);

        if (!$command instanceof Command) {
            throw new UnexpectedValueException(sprintf(
                'Container entry %s must resolve to %s, %s returned.',
                $className,
                Command::class,
                get_debug_type($command),
            ));
        }

        return $command;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->commands);
    }

    /**
     * @return list<non-empty-string>
     */
    public function getNames(): array
    {
        return array_keys($this->commands);
    }
}
