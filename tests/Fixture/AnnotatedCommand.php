<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Fixture;

use SuperKernel\Command\Attribute\Command as AttributeCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AttributeCommand(name: 'app:annotated', description: 'A framework command.', aliases: [
    'annotated',
])]
final class AnnotatedCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('annotated');

        return self::SUCCESS;
    }
}
