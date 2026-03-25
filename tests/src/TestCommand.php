<?php
declare(strict_types=1);

namespace SuperKernelTest\Command;

use SuperKernel\Command\AbstractCommand;
use SuperKernel\Command\Annotation\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[
	Command(
		name       : 'test',
		description: 'The test command to run.',
	),
]
final class TestCommand extends AbstractCommand
{
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('Test command started');

		return SymfonyCommand::SUCCESS;
	}
}