<?php

declare(strict_types=1);

namespace SuperKernel\Command\Listener;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Event\BeforeApplicationStart;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

#[Listener]
final readonly class BeforeApplicationStartListener implements ListenerInterface
{
    public function __construct(private CommandLoaderInterface $commandLoader)
    {
    }

    public function listen(): array
    {
        return [
            BeforeApplicationStart::class,
        ];
    }

    /**
     * @psalm-param BeforeApplicationStart $event
     */
    public function process(object $event): void
    {
        $application = $event->application();

        if ($application instanceof Application) {
            $application->setCommandLoader($this->commandLoader);
        }
    }
}
