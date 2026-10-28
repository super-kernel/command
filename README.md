# SuperKernel Command

`super-kernel/command` discovers exact class-level `SuperKernel\Command\Attribute\Command` declarations and exposes a lazy
`CommandLoaderInterface` through SuperKernel's shared DI `FactoryProvider` metadata. Commands are resolved from the
shared container only when Symfony initializes the selected command for execution, help, or completion.

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

The final readonly Attribute accepts only `string $name`, `?string $description = null`,
`array $aliases = []`, and `bool $hidden = false`. The name is required and must be nonempty; aliases must be a
list of nonempty strings. Registration rejects duplicate names or aliases, including duplicates within one declaration.
Construction stores the values; the provider validates them so an empty name fails registration rather than becoming
a skipped-class diagnostic. PHP type, target, and repeatability errors still follow the scanner's rules.

`CommandLoaderProvider` queries only the exact `SuperKernel\Command\Attribute\Command` type through
`AnnotationCollectorInterface::getClassesWithAttribute()`. It does not query assignable types, check package names,
or use Symfony `AsCommand` as a registration signal. Only selected targets undergo the Symfony Command inheritance
check; other targets are not loaded by registration. Discovery does not instantiate commands.

The provider passes the validated framework declarations directly to `CommandLoader`. The `name` field is the
primary name, `aliases` supplies additional names, and `hidden` determines visibility. Method-level declarations
are not supported. The factory receives `Psr\Container\ContainerInterface` and `AnnotationCollectorInterface`
through its two-parameter invocation and has no constructor dependencies or retained state.

The resulting loader exposes names without resolving command dependencies. Its required third constructor argument
is an `array<class-string<SymfonyCommand>, SuperKernel\Command\Attribute\Command>` of validated declarations.
`get()` returns Symfony `LazyCommand` objects carrying the collected name, aliases,
description, and hidden flag. Listing commands and suggesting names for an unknown command do not resolve dependencies.
When Symfony initializes a selected command, the loader asks the injected container for that class and verifies the
resolved entry; resolution failures propagate. A mapped class without a declaration is rejected without resolution. The container owns command identity and lifecycle.

Symfony's built-in `help`, `list`, `completion`, and `_complete` are provided by its Application. Tool commands can be
run through the tool's own executable, such as `php vendor/bin/php-cs-fixer check`, or through an application
adapter marked with this component's Attribute and using the library's public services.

Default scan logs go to stdout under DI's diagnostic policy. Applications needing machine-readable stdout should
provide a stdout logger with a stderr transport; Command does not change Logger defaults.

## Framework integration

`SuperKernel\Command\Provider\CommandLoaderProvider` maps Symfony's `CommandLoaderInterface` with
`#[FactoryProvider(...)]`. `SuperKernel\Command\Listener\BeforeApplicationStartListener`, discovered through
`#[Listener]`, receives that loader and listens for `SuperKernel\Event\BeforeApplicationStart`. It uses the event's
application directly and calls `setCommandLoader()` only when that object is also a
`Symfony\Component\Console\Application`; other `ApplicationInterface` implementations are left untouched.

The Framework application factory dispatches this event synchronously after creating the application and installing
its dispatcher, before returning it. Attaching the loader does not resolve commands. The listener never resolves
`ApplicationInterface` from the container, so application creation does not recursively request the same application.
Command depends on the Event declarations and its existing Symfony Console dependency, without depending on the
Framework or Event Dispatcher implementations.

## Development

Use a PHP 8.4 Composer in this package's checkout:

```bash
composer install
composer validate --strict
composer test
composer format:check
composer format
```
