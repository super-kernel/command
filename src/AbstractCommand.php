<?php
declare(strict_types=1);

namespace SuperKernel\Command;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Command\Annotation\Command;
use SuperKernel\Contract\AnnotationCollectorInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

abstract class AbstractCommand extends SymfonyCommand
{
	/**
	 * @param ContainerInterface $container
	 *
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(protected readonly ContainerInterface $container)
	{
		/* @var AnnotationCollectorInterface $annotationCollector */
		$annotationCollector = $this->container->get(AnnotationCollectorInterface::class);

		$annotations = $annotationCollector->getAnnotationsByClass(static::class);
		foreach ($annotations as $annotation) {
			if ($annotation->getName() !== Command::class) {
				continue;
			}

			$command = $annotation->getInstance();

			$this->setName($command->name);

			if (null !== $command->description) {
				$this->setDescription($command->description);
			}

			if (null !== $command->help) {
				$this->setHelp($command->help);
			}

			foreach ($command->usages as $usage) {
				$this->addUsage($usage);
			}
		}

		parent::__construct();
	}
}