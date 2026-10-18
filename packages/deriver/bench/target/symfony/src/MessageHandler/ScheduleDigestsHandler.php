<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\{ScheduleDigests,SendDigest};
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\{Attribute\AsMessageHandler,MessageBusInterface};

#[AsMessageHandler]
final class ScheduleDigestsHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MessageBusInterface $bus)
    {
    }
    public function __invoke(ScheduleDigests $message): void
    {
        $period = (new DateTimeImmutable('yesterday', new DateTimeZone('UTC')))->format('Y-m-d');
        foreach ($this->em->getRepository(User::class)->findBy(['digestEnabled' => true]) as $user) {
            if ($user->active() && $user->verifiedAt !== null) {
                $this->bus->dispatch(new SendDigest($user->id, $period));
            }
        }
    }
}
