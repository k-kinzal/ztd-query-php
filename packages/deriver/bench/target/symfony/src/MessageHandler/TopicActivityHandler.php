<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\{Notification,Subscription,Topic};
use App\Message\TopicActivity;
use App\Security\Access;
use App\Service\PublishingRules;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TopicActivityHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Access $access, private readonly PublishingRules $rules)
    {
    }
    public function __invoke(TopicActivity $message): void
    {
        $topic = $this->em->find(Topic::class, $message->topicId);
        if ($topic === null) {
            return;
        }
        $this->em->wrapInTransaction(function () use ($topic, $message): void {
            $this->em->lock($topic, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $key = $topic->id.':'.$message->version.':'.$message->event;
            foreach ($this->em->getRepository(Subscription::class)->findBy(['topic' => $topic]) as $subscription) {
                $user = $subscription->user;
                if ($user->id === $message->actorId || !$user->active() || !$this->access->viewTopic($topic, $user) || $this->em->getRepository(Notification::class)->findOneBy(['user' => $user,'eventKey' => $key])) {
                    continue;
                }
                $notification = new Notification();
                $notification->user = $user;
                $notification->topic = $topic;
                $notification->eventKey = $key;
                $notification->message = $this->rules->notificationMessage($message->event);
                $this->em->persist($notification);
            }
        });
    }
}
