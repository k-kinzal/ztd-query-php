<?php

declare(strict_types=1);

namespace HarborOps\Import;

use HarborOps\Audit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ImportAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Audit $audit)
    {
    }
    public static function getSubscribedEvents(): array
    {
        return [PostImported::class => 'imported'];
    }
    public function imported(PostImported $event): void
    {
        $this->audit->record('post.imported', (string) $event->id, ['community_id' => $event->communityId, 'external_id' => $event->externalId]);
    }
}
