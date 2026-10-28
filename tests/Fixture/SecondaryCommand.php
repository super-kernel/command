<?php

declare(strict_types=1);

namespace SuperKernel\Command\Tests\Fixture;

use SuperKernel\Command\Attribute\Command as AttributeCommand;
use Symfony\Component\Console\Command\Command;

#[AttributeCommand(name: 'app:secondary')]
final class SecondaryCommand extends Command
{
}
