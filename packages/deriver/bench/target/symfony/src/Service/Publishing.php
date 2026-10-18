<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\{EditPost,PublishTopic,TransitionTopic};
use App\Entity\{Board,Post,PostRevision,PublicationReceipt,Subscription,Topic,User};
use App\Enum\TopicState;
use App\Message\TopicActivity;
use App\Repository\MembershipRepository;
use App\Security\Access;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,ConflictHttpException};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final class Publishing
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Access $access, private readonly MembershipRepository $memberships, private readonly Audit $audit, private readonly PublishingRules $rules, private readonly MessageBusInterface $bus, #[Autowire(service:'state_machine.topic')] private readonly WorkflowInterface $workflow)
    {
    }
    public function create(Board $board, User $actor, PublishTopic $input): Topic
    {
        if (!$this->access->publish($board, $actor)) {
            throw new AccessDeniedHttpException('Posting permission is required.');
        }
        return $this->em->wrapInTransaction(function () use ($board, $actor, $input): Topic {
            $this->em->lock($actor, LockMode::PESSIMISTIC_WRITE);
            $fingerprint = hash('sha256', json_encode([$board->id,$input->title,$input->body], JSON_THROW_ON_ERROR));
            $receipt = $this->em->getRepository(PublicationReceipt::class)->findOneBy(['user' => $actor,'requestKey' => $input->requestKey]);
            if ($receipt !== null) {
                if (!hash_equals($receipt->fingerprint, $fingerprint)) {
                    throw new ConflictHttpException('The request key belongs to different content.');
                }return $receipt->topic;
            }
            $topic = new Topic();
            $topic->board = $board;
            $topic->author = $actor;
            $topic->title = $input->title;
            $topic->status = TopicState::from($this->rules->initialState($board->requiresReview, $this->memberships->role($board->community, $actor)->value));
            $this->em->persist($topic);
            $post = new Post();
            $post->topic = $topic;
            $post->author = $actor;
            $post->body = $input->body;
            $this->em->persist($post);
            $subscription = new Subscription();
            $subscription->topic = $topic;
            $subscription->user = $actor;
            $this->em->persist($subscription);
            $receipt = new PublicationReceipt();
            $receipt->topic = $topic;
            $receipt->user = $actor;
            $receipt->requestKey = $input->requestKey;
            $receipt->fingerprint = $fingerprint;
            $this->em->persist($receipt);
            $this->em->flush();
            $this->audit->record($actor, $board->community, 'topic.created', (string)$topic->id);
            $this->bus->dispatch(new TopicActivity($topic->id, $actor->id, $topic->version, 'created'));
            return $topic;
        });
    }
    public function reply(Topic $topic, User $actor, string $body): Post
    {
        if (!$this->access->publish($topic->board, $actor) || $topic->status !== TopicState::Published) {
            throw new AccessDeniedHttpException('This discussion is not open for replies.');
        }
        return $this->em->wrapInTransaction(function () use ($topic, $actor, $body): Post {
            $this->em->lock($topic, LockMode::PESSIMISTIC_WRITE);
            $post = new Post();
            $post->topic = $topic;
            $post->author = $actor;
            $post->body = $body;
            $this->em->persist($post);
            $topic->updatedAt = new DateTimeImmutable();
            if (!$this->em->getRepository(Subscription::class)->findOneBy(['topic' => $topic,'user' => $actor])) {
                $subscription = new Subscription();
                $subscription->topic = $topic;
                $subscription->user = $actor;
                $this->em->persist($subscription);
            }
            $this->em->flush();
            $this->bus->dispatch(new TopicActivity($topic->id, $actor->id, $topic->version, 'reply'));
            return $post;
        });
    }
    public function edit(Post $post, User $actor, EditPost $input): Post
    {
        if (!$this->access->edit($post, $actor)) {
            throw new AccessDeniedHttpException('The editing window is closed or permission is missing.');
        }
        if ($post->version !== $input->version) {
            throw new ConflictHttpException('The post has changed.');
        }
        return $this->em->wrapInTransaction(function () use ($post, $actor, $input): Post {
            $this->em->lock($post, LockMode::OPTIMISTIC, $input->version);
            $revision = new PostRevision();
            $revision->post = $post;
            $revision->editor = $actor;
            $revision->body = $post->body;
            $revision->reason = $input->reason;
            $this->em->persist($revision);
            $post->body = $input->body;
            $this->audit->record($actor, $post->topic->board->community, 'post.edited', (string)$post->id, ['reason' => $input->reason]);
            return $post;
        });
    }
    public function transition(Topic $topic, User $actor, TransitionTopic $input): Topic
    {
        if (!$this->access->moderate($topic->board->community, $actor)) {
            throw new AccessDeniedHttpException();
        }
        if ($topic->version !== $input->version || !$this->workflow->can($topic, $input->action)) {
            throw new ConflictHttpException('The topic changed or this transition is unavailable.');
        }
        return $this->em->wrapInTransaction(function () use ($topic, $actor, $input): Topic {
            $this->em->lock($topic, LockMode::OPTIMISTIC, $input->version);
            $this->workflow->apply($topic, $input->action);
            $topic->updatedAt = new DateTimeImmutable();
            $this->em->flush();
            $this->audit->record($actor, $topic->board->community, 'topic.'.$input->action, (string)$topic->id);
            $this->bus->dispatch(new TopicActivity($topic->id, $actor->id, $topic->version, $input->action));
            return $topic;
        });
    }
}
