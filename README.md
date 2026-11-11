<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Command

`super-kernel/command` registers the Symfony Console commands that classes declare with its `#[Command]` Attribute, and creates each one through the container only when it is used.

## Installation

```bash
composer require super-kernel/command
```

Commands run in the application of `super-kernel/framework`, which needs the container and the other packages it lists; `super-kernel/skeleton` installs all of them. The commands are found in the metadata that `skernel build` generates, so a project needs SKERNEL to build it.

## What it provides

- Commands declared with `#[Command]` on a class that extends Symfony's `Command`, with a name, a description, aliases, and a hidden flag.
- Constructor injection into commands, because the container creates them.
- Lazy commands: listing commands and suggesting names create none of them.
- Rejection of empty names, invalid aliases, duplicate names, and classes that are not commands.
- The command loader installed in the application when it starts.

It does not:

- register commands declared with Symfony's `#[AsCommand]`;
- register methods as commands;
- create the application or run commands; `super-kernel/framework` and Symfony Console do.

## Quick start

Declare a command. Save it as `src/Command/HelloCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Command;

use SuperKernel\Command\Attribute\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command(name: 'app:hello', description: 'Print a greeting.', aliases: ['hello'])]
final class HelloCommand extends SymfonyCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello');

        return self::SUCCESS;
    }
}
```

Build the project and run the command by its alias:

```bash
skernel build
php bin/bootstrap.php hello
```

It prints `Hello`.

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\Command\Attribute\Command` | Attribute | Registers the annotated Symfony command under a name, with an optional description, aliases, and hidden flag. |
| `Symfony\Component\Console\CommandLoader\CommandLoaderInterface` | Interface | Provided by the container; loads the registered commands lazily. |

## Behavior to know

- Only `#[Command]` on a class registers a command; a class with only Symfony's `#[AsCommand]` is not registered.
- A command is created through the container when Symfony initializes it to run it, show its help, or complete it; a failure to create it is reported then, and the run ends with a non-zero status.
- An empty name, aliases that are not a list of non-empty strings, a name or alias used twice, or a class that does not extend Symfony's `Command` raises `InvalidArgumentException` when the loader is created, which fails `skernel build` and the application start. Duplicates name both classes: `Command name "<name>" is declared by both <class> and <class>.`
- Commands are found in the build metadata: run `skernel build` again after adding, removing, or changing a command, or start with `skernel run`, which builds first.

## Documentation

The developer documentation describes the declaration rules, injecting services into commands, built-in and tool commands, and every error message: [SuperKernel Command](https://github.com/super-kernel/.github/blob/main/docs/components/command.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
