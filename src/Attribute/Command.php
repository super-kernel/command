<?php

declare(strict_types=1);

namespace SuperKernel\Command\Attribute;

use Attribute;

/** Declares a Console command; registration validates its name and aliases. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Command
{
    /** @param list<string> $aliases */
    public function __construct(
        public string  $name,
        public ?string $description = null,
        public array   $aliases = [],
        public bool    $hidden = false,
    )
    {
    }
}
