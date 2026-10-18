<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\{DigestDelivery,Notification,User};
use App\Message\SendDigest;
use App\Security\Access;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class SendDigestHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MailerInterface $mailer, private readonly LockFactory $locks, private readonly Access $access, private readonly string $sender)
    {
    }
    public function __invoke(SendDigest $message): void
    {
        $lock = $this->locks->createLock('digest-'.$message->userId.'-'.$message->period, 300);
        if (!$lock->acquire()) {
            throw new RuntimeException('Another worker is sending this digest.');
        }
        try {
            $user = $this->em->find(User::class, $message->userId);
            if ($user === null || !$user->active() || !$user->digestEnabled || $user->verifiedAt === null) {
                return;
            }
            $delivery = $this->em->getRepository(DigestDelivery::class)->findOneBy(['user' => $user,'period' => $message->period]);
            if ($delivery?->sentAt !== null) {
                return;
            }
            if ($delivery === null) {
                $delivery = new DigestDelivery();
                $delivery->user = $user;
                $delivery->period = $message->period;
                $this->em->persist($delivery);
                $this->em->flush();
            }
            $start = new DateTimeImmutable($message->period.'T00:00:00+00:00');
            $notifications = $this->em->createQueryBuilder()->select('n', 't')->from(Notification::class, 'n')->join('n.topic', 't')->where('n.user=:user AND n.createdAt>=:start AND n.createdAt<:end')->setParameter('user', $user)->setParameter('start', $start)->setParameter('end', $start->modify('+1 day'))->setMaxResults(100)->getQuery()->getResult();
            $lines = [];
            foreach ($notifications as $notification) {
                if ($this->access->viewTopic($notification->topic, $user)) {
                    $lines[] = $notification->topic->title.' — '.$notification->message;
                }
            }
            if ($lines !== []) {
                $mail = (new Email())->from($this->sender)->to($user->email)->subject('Harbor discussion digest · '.$message->period)->text(implode("\n\n", $lines));
                $mail->getHeaders()->addIdHeader('Message-ID', 'harbor-digest-'.$delivery->id.'@'.substr(strrchr($this->sender, '@'), 1));
                $this->mailer->send($mail);
            }
            $delivery->sentAt = new DateTimeImmutable();
            $this->em->flush();
        } finally {
            $lock->release();
        }
    }
}
