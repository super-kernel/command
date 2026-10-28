<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Fixture;

use SuperKernel\Command\Attribute\Command as AttributeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AttributeCommand(
    name: 'app:registered',
    description: 'A registered test command.',
    aliases: [
        'app:alias',
    ],
)]
final class RegisteredCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('registered');

        return self::SUCCESS;
    }
}
