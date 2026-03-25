<?php
declare(strict_types=1);

namespace SuperKernel\Command\Listener;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Attribute\Listener;
use SuperKernel\Command\AbstractCommand;
use SuperKernel\Command\Annotation\Command;
use SuperKernel\Command\Exception\InvalidCommandException;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\ApplicationInterface;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Framework\Event\BootApplication;
use Symfony\Component\Console\Application;
use function is_subclass_of;

#[Listener]
final readonly class CommandListener implements ListenerInterface
{
	public function listen(): array
	{
		return [
			BootApplication::class,
		];
	}

	public function __construct(private ContainerInterface $container)
	{
	}

	/**
	 * @param object $event
	 *
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function process(object $event): void
	{
		/* @var Application $application */
		$application = $this->container->get(ApplicationInterface::class);
		/* @var AnnotationCollectorInterface $annotationCollector */
		$annotationCollector = $this->container->get(AnnotationCollectorInterface::class);

		foreach ($annotationCollector->getClassesByAttribute(Command::class) as $attribute) {
			/* @var class-string $class */
			$class = $attribute->getClass();

			if (!is_subclass_of($class, AbstractCommand::class)) {
				throw InvalidCommandException::create($class);
			}

			$command = $this->container->get($class);

			$application->addCommand($command);
		}
	}
}