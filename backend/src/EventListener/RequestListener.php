<?php

namespace EventListener;

use Broadway\Domain\DomainMessage;
use Broadway\EventHandling\EventListener;
use Enqueue\Client\ProducerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RequestListener implements EventListener
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onCommandHandlingSuccess($event)
    {
        if ($event instanceof DomainMessage) {
            $this->container->get(ProducerInterface::class)->sendEvent(
                $event->getType(),
                [
                    'id' => $event->getId(),
                    'event' => $event->getType(),
                    'playhead' => $event->getPlayhead(),
                    'metadata' => $event->getMetadata()->all(),
                    'payload' => $event->getPayload()->serialize(),
                    'recordOn' => $event->getRecordedOn()->toString()
                ]
            );
        }
    }

    public function handle(DomainMessage $domainMessage)
    {
        $this->onCommandHandlingSuccess($domainMessage);
    }
}