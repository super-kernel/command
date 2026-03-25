<?php
declare(strict_types=1);

namespace SuperKernel\Command\Exception;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use function sprintf;

final class InvalidCommandException extends RuntimeException
{
	public static function create(string $class): self
	{
		return new self(
			sprintf(
				'Command class "%s" must extend %s.',
				$class,
				Command::class,
			),
		);
	}
}