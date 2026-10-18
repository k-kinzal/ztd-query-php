<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\RegisterAccount;
use App\Entity\{Notification,Post,User};
use App\Service\{EmailVerification,Privacy};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AccountController extends AbstractController
{
    #[Route('/api/session', methods:['GET'])]
    public function session(CsrfTokenManagerInterface $tokens): JsonResponse
    {
        return $this->json(['user' => $this->getUser()?->getUserIdentifier(),'csrf' => (string)$tokens->getToken('api')]);
    }
    #[Route('/api/register', methods:['POST'], format:'json')]
    public function register(#[MapRequestPayload] RegisterAccount $input, Request $request, #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service:'limiter.registration')] RateLimiterFactory $registrationLimiter, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, EmailVerification $verification): JsonResponse
    {
        if (!$registrationLimiter->create($request->getClientIp() ?? '')->consume()->isAccepted()) {
            throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(3600);
        }
        if ($em->getRepository(User::class)->findOneBy(['email' => strtolower($input->email)])) {
            return $this->json(['error' => 'This email is already registered.'], 409);
        }
        $user = new User($input->email, $input->name);
        $user->setPassword($hasher->hashPassword($user, $input->password));
        $em->persist($user);
        $em->flush();
        $verification->send($user);
        return $this->json(['id' => $user->id,'verification_required' => true], 201);
    }
    #[Route('/verify/{id}/{token}', name:'verify', methods:['GET'])]
    public function verify(User $user, string $token, EmailVerification $verification): Response
    {
        $verification->verify($user, $token);
        return $this->redirectToRoute('login');
    }
    #[Route('/api/verification', methods:['POST'])]
    public function resend(#[CurrentUser] User $user, EmailVerification $verification): JsonResponse
    {
        if ($user->verifiedAt === null) {
            $verification->send($user);
        }return $this->json(['sent' => true]);
    }
    #[Route('/login', name:'login', methods:['GET','POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        return $this->render('account/login.html.twig', ['error' => $authentication->getLastAuthenticationError(),'last_username' => $authentication->getLastUsername()]);
    }
    #[Route('/api/login', methods:['POST'])]
    public function apiLogin(#[CurrentUser] ?User $user): JsonResponse
    {
        return $this->json(['user' => $user?->id], $user === null ? 401 : 200);
    }
    #[Route('/logout', name:'logout', methods:['POST'])]
    public function logout(): never
    {
        throw new LogicException('The firewall handles logout.');
    }
    #[Route('/api/notifications', methods:['GET'])]
    public function notifications(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        return $this->json(array_map(static fn (Notification $n) => ['id' => $n->id,'topic_id' => $n->topic->id,'message' => $n->message,'read' => $n->readAt !== null], $em->getRepository(Notification::class)->findBy(['user' => $user], ['id' => 'DESC'], 50)));
    }
    #[Route('/api/notifications/{id}', methods:['PUT'])]
    public function read(Notification $notification, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if ($notification->user->id !== $user->id) {
            throw $this->createNotFoundException();
        }$notification->readAt = new DateTimeImmutable();
        $em->flush();
        return new Response('', 204);
    }
    #[Route('/api/account/digest', methods:['PUT'])]
    public function digest(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $enabled = $request->toArray()['enabled'] ?? null;
        if (!is_bool($enabled)) {
            return $this->json(['error' => 'enabled must be a boolean'], 422);
        }$user->digestEnabled = $enabled;
        $em->flush();
        return new Response('', 204);
    }
    #[Route('/api/account/export', methods:['GET'])]
    public function export(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        return $this->json(['account' => ['id' => $user->id,'name' => $user->displayName,'email' => $user->email],'posts' => array_map(static fn (Post $p) => ['id' => $p->id,'body' => $p->body], $em->getRepository(Post::class)->findBy(['author' => $user]))]);
    }
    #[Route('/api/account', methods:['DELETE'])]
    public function erase(Request $request, #[CurrentUser] User $user, Privacy $privacy): Response
    {
        $password = $request->toArray()['password'] ?? '';
        if (!is_string($password)) {
            return $this->json(['error' => 'password must be a string'], 422);
        }$privacy->erase($user, $password);
        $request->getSession()->invalidate();
        return new Response('', 204);
    }
}
