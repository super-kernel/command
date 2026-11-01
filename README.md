<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Command

`super-kernel/command` registers Symfony Console commands declared with its `#[Command]` Attribute. Commands are loaded lazily: each one is resolved from the shared container only when Symfony initializes it for execution, help, or completion.

## Registering a command

Extend Symfony's `Command` and use this component's final `Command` Attribute. Alias the Symfony base class to avoid the name collision:

```php
use SuperKernel\Command\Attribute\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command(
    name: 'app:hello',
    description: 'Print a greeting.',
    aliases: ['hello'],
)]
final class HelloCommand extends SymfonyCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello');

        return self::SUCCESS;
    }
}
```

The final readonly Attribute accepts only `string $name`, `?string $description = null`, `array $aliases = []`, and `bool $hidden = false`. The `name` is the primary name, `aliases` supplies additional names, and `hidden` hides the command from listings.

- The name is required and must be nonempty; aliases must be a list of nonempty strings.
- Registration rejects duplicate names or aliases, including duplicates within one declaration, and classes that do not extend Symfony's `Command`, with `InvalidArgumentException`. These errors fail registration rather than becoming skipped-class diagnostics; PHP type, target, and repeatability errors follow the annotation scanner's rules.
- Only the exact `#[Command]` class-level Attribute registers a command. Symfony's `#[AsCommand]` is not a registration signal, and method-level declarations are not supported.

Discovery does not instantiate commands. Listing commands and suggesting names for an unknown command do not resolve dependencies. When Symfony initializes a selected command, the container resolves that class and the result is verified; resolution failures propagate. The container owns command identity and lifecycle.

Symfony's built-in `help`, `list`, `completion`, and `_complete` are provided by its Application. Tool commands can be run through the tool's own executable, such as `php vendor/bin/php-cs-fixer check`, or through an application adapter marked with this component's Attribute and using the library's public services.

Default scan logs go to stdout under DI's diagnostic policy. Applications needing machine-readable stdout should provide a stdout logger with a stderr transport; Command does not change Logger defaults.

## Framework integration

The container supplies Symfony's `CommandLoaderInterface`. When the Framework application factory dispatches `SuperKernel\Event\BeforeApplicationStart`, this component attaches that loader to the event's application if it is a `Symfony\Component\Console\Application`; other `ApplicationInterface` implementations are left untouched. Attaching the loader does not resolve commands.
