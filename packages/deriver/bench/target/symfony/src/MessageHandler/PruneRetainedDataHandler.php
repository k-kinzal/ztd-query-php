<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\{AuditEntry,DigestDelivery,Notification,PublicationReceipt};
use App\Message\PruneRetainedData;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PruneRetainedDataHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }
    public function __invoke(PruneRetainedData $message): void
    {
        foreach ([[AuditEntry::class,'-1 year',false],[PublicationReceipt::class,'-1 day',false],[DigestDelivery::class,'-90 days',false],[Notification::class,'-90 days',true]] as [$class,$age,$readOnly]) {
            $query = $this->em->createQueryBuilder()->delete($class, 'r')->where('r.createdAt<:cutoff')->setParameter('cutoff', new DateTimeImmutable($age));
            if ($readOnly) {
                $query->andWhere('r.readAt IS NOT NULL');
            }$query->getQuery()->execute();
        }
    }
}
