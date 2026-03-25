<?php
declare(strict_types=1);

namespace SuperKernel\Command\Annotation;

use Attribute;
use Symfony\Component\Console\Attribute\AsCommand;
use function array_merge;
use function array_unshift;
use function explode;
use function implode;

/**
 * @mixin AsCommand
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Command
{
	/**
	 * @param string      $name        The name of the command, used when calling it (i.e. "cache:clear")
	 * @param string|null $description The description of the command, displayed with the help page
	 * @param string[]    $aliases     The list of aliases of the command. The command will be executed when using one
	 *                                 of them (i.e. "cache:clean")
	 * @param bool        $hidden      If true, the command won't be shown when listing all the available commands, but
	 *                                 it can still be run as any other command
	 * @param string|null $help        The help content of the command, displayed with the help page
	 * @param string[]    $usages      The list of usage examples, displayed with the help page
	 */
	public function __construct(
		public string  $name,
		public ?string $description = null,
		array          $aliases = [],
		bool           $hidden = false,
		public ?string $help = null,
		public array   $usages = [],
	)
	{
		if (!$hidden && !$aliases) {
			return;
		}

		$name = explode('|', $name);
		$name = array_merge($name, $aliases);

		if ($hidden && '' !== $name[0]) {
			array_unshift($name, '');
		}

		$this->name = implode('|', $name);
	}
}