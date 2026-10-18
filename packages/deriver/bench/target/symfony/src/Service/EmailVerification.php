<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class EmailVerification
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MailerInterface $mailer, private readonly string $sender, private readonly string $baseUrl)
    {
    }
    public function send(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->verificationHash = hash('sha256', $token);
        $user->verificationExpiresAt = new DateTimeImmutable('+1 day');
        $this->em->flush();
        $link = rtrim($this->baseUrl, '/').'/verify/'.$user->id.'/'.$token;
        $this->mailer->send((new Email())->from($this->sender)->to($user->email)->subject('Verify your Harbor account')->text("Verify your email address using this link, valid for one day:\n".$link));
    }
    public function verify(User $user, string $token): void
    {
        if ($user->verificationHash === null || $user->verificationExpiresAt < new DateTimeImmutable() || !hash_equals($user->verificationHash, hash('sha256', $token))) {
            throw new BadRequestHttpException('The verification link is invalid or expired.');
        }
        $user->verifiedAt = new DateTimeImmutable();
        $user->verificationHash = null;
        $user->verificationExpiresAt = null;
        $this->em->flush();
    }
}
