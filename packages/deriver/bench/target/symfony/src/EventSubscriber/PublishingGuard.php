<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Topic;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

final class PublishingGuard implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['workflow.topic.guard.approve' => 'guard','workflow.topic.guard.unlock' => 'guard','workflow.topic.guard.restore' => 'guard'];
    }
    public function guard(GuardEvent $event): void
    {
        $topic = $event->getSubject();
        if ($topic instanceof Topic && $topic->board->archivedAt !== null) {
            $event->setBlocked(true, 'Archived boards cannot accept published discussions.');
        }
    }
}
