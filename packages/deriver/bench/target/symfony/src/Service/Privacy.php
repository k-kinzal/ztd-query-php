<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Community,Membership,Notification,Post,PostRevision,Reaction,Report,Subscription,User};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,ConflictHttpException};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class Privacy
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $hasher, private readonly Audit $audit)
    {
    }
    public function erase(User $user, string $password): void
    {
        if (!$this->hasher->isPasswordValid($user, $password)) {
            throw new AccessDeniedHttpException('The current password is required.');
        }
        if ($this->em->getRepository(Community::class)->findOneBy(['owner' => $user])) {
            throw new ConflictHttpException('Transfer community ownership before erasing your account.');
        }
        $this->em->wrapInTransaction(function () use ($user): void {
            foreach ($this->em->getRepository(\App\Entity\Topic::class)->findBy(['author' => $user]) as $topic) {
                $topic->title = '[removed by author]';
            }
            foreach ($this->em->getRepository(Post::class)->findBy(['author' => $user]) as $post) {
                foreach ($this->em->getRepository(PostRevision::class)->findBy(['post' => $post]) as $revision) {
                    $this->em->remove($revision);
                }
                $post->body = '[removed by author]';
                $post->hiddenAt = new DateTimeImmutable();
            }
            foreach ([Membership::class,Subscription::class,Notification::class,Reaction::class] as $class) {
                foreach ($this->em->getRepository($class)->findBy(['user' => $user]) as $record) {
                    $this->em->remove($record);
                }
            }
            foreach ($this->em->getRepository(Report::class)->findBy(['reporter' => $user]) as $report) {
                $report->reason = '[removed by reporter]';
            }
            $user->displayName = 'Deleted member';
            $user->email = 'erased-'.$user->id.'@invalid.local';
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(64))));
            $user->verifiedAt = null;
            $user->digestEnabled = false;
            $user->verificationHash = null;
            $user->suspendedUntil = new DateTimeImmutable('+100 years');
            $this->audit->record(null, null, 'account.erased', (string)$user->id);
        });
    }
}
