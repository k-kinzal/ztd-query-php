<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\{EditPost,PublishTopic,Reply,TransitionTopic};
use App\Entity\{Board,Post,Reaction,Subscription,Topic,User};
use App\Http\Representation;
use App\Repository\DiscussionRepository;
use App\Service\Publishing;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse,Request,Response};
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
final class DiscussionController extends AbstractController
{
    public function __construct(private readonly Representation $view)
    {
    }
    #[Route('/boards/{id}/topics', methods:['GET'])]
    public function index(Board $board, Request $request, DiscussionRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted('VIEW', $board);
        return $this->json(array_map($this->view->topic(...), $repository->page($board, max(1, $request->query->getInt('before', PHP_INT_MAX)), mb_substr($request->query->getString('q'), 0, 100))));
    }
    #[Route('/boards/{id}/topics', methods:['POST'], format:'json')]
    public function create(Board $board, #[MapRequestPayload] PublishTopic $input, #[CurrentUser] User $user, Publishing $publishing, #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service:'limiter.publishing')] RateLimiterFactory $publishingLimiter): JsonResponse
    {
        if (!$publishingLimiter->create((string)$user->id)->consume()->isAccepted()) {
            throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(60);
        }return $this->json($this->view->topic($publishing->create($board, $user, $input)), 201);
    }
    #[Route('/topics/{id}', methods:['GET'])]
    public function show(Topic $topic): JsonResponse
    {
        $this->denyAccessUnlessGranted('VIEW', $topic);
        return $this->json($this->view->topic($topic));
    }
    #[Route('/topics/{id}/posts', methods:['GET'])]
    public function posts(Topic $topic, Request $request, DiscussionRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted('VIEW', $topic);
        return $this->json(array_map($this->view->post(...), $repository->posts($topic, max(0, $request->query->getInt('after')))));
    }
    #[Route('/topics/{id}/replies', methods:['POST'], format:'json')]
    public function reply(Topic $topic, #[MapRequestPayload] Reply $input, #[CurrentUser] User $user, Publishing $publishing): JsonResponse
    {
        return $this->json($this->view->post($publishing->reply($topic, $user, $input->body)), 201);
    }
    #[Route('/posts/{id}', methods:['PUT'], format:'json')]
    public function edit(Post $post, #[MapRequestPayload] EditPost $input, #[CurrentUser] User $user, Publishing $publishing): JsonResponse
    {
        return $this->json($this->view->post($publishing->edit($post, $user, $input)));
    }
    #[Route('/topics/{id}/transition', methods:['POST'], format:'json')]
    public function transition(Topic $topic, #[MapRequestPayload] TransitionTopic $input, #[CurrentUser] User $user, Publishing $publishing): JsonResponse
    {
        return $this->json($this->view->topic($publishing->transition($topic, $user, $input)));
    }
    #[Route('/topics/{id}/subscription', methods:['PUT','DELETE'])]
    public function subscribe(Topic $topic, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $topic);
        $subscription = $em->getRepository(Subscription::class)->findOneBy(['topic' => $topic,'user' => $user]);
        if ($request->isMethod('DELETE') && $subscription !== null) {
            $em->remove($subscription);
        } elseif ($request->isMethod('PUT') && $subscription === null) {
            $subscription = new Subscription();
            $subscription->topic = $topic;
            $subscription->user = $user;
            $em->persist($subscription);
        }
        $em->flush();
        return new Response('', 204);
    }
    #[Route('/posts/{id}/reaction', methods:['PUT'])]
    public function react(Post $post, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $post);
        $kind = $request->toArray()['kind'] ?? null;
        if ($post->author->id === $user->id || !in_array($kind, ['thanks','helpful','agree'], true)) {
            return $this->json(['error' => 'Choose a supported reaction on another members post.'], 422);
        }
        $reaction = $em->getRepository(Reaction::class)->findOneBy(['post' => $post,'user' => $user]) ?? new Reaction();
        $reaction->post = $post;
        $reaction->user = $user;
        $reaction->kind = $kind;
        $em->persist($reaction);
        $em->flush();
        return new Response('', 204);
    }
    #[Route('/boards/{id}/archive', methods: ['PUT'])]
    public function archiveBoard(Board $board, Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, \App\Service\Audit $audit): Response
    {
        $this->denyAccessUnlessGranted('MODERATE', $board->community);
        $archived = $request->toArray()['archived'] ?? null;
        if (!is_bool($archived)) {
            return $this->json(['error' => 'archived must be a boolean'], 422);
        }
        $board->archivedAt = $archived ? new DateTimeImmutable() : null;
        $audit->record($user, $board->community, $archived ? 'board.archived' : 'board.reopened', (string) $board->id);
        $em->flush();
        return new Response('', 204);
    }

}
