<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dto\{CreateBoard,CreateCommunity,EditPost,FileReport,PublishTopic,ResolveReport,RestrictMember,TransitionTopic};
use App\Entity\{Community,Membership,Notification,Post,PostRevision,Topic,User};
use App\Enum\{TopicState};
use App\Message\TopicActivity;
use App\MessageHandler\TopicActivityHandler;
use App\Service\{CommunityManagement,Moderation,Privacy,Publishing};
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface,Tools\SchemaTool};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\{Request,Session\Session,Session\Storage\MockArraySessionStorage};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,ConflictHttpException};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class CommunityTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }
    private function user(string $name): User
    {
        $user = new User($name.'@example.test', ucfirst($name));
        $user->verifiedAt = new DateTimeImmutable();
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'correct-horse-battery-staple'));
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
    private function board(bool $review = false, string $visibility = 'public'): array
    {
        $owner = $this->user('owner');
        $service = self::getContainer()->get(CommunityManagement::class);
        $community = $service->create($owner, new CreateCommunity('engineering', 'Engineering', 'Engineering operations and support community'));
        $board = $service->board($community, $owner, new CreateBoard('support', 'Support', 'Community support discussions', $visibility, $review));
        return [$owner,$community,$board];
    }
    private function member(Community $community, string $name): User
    {
        $user = $this->user($name);
        self::getContainer()->get(CommunityManagement::class)->join($community, $user);
        return $user;
    }
    public function testPublicationAndMessengerNotificationAreIdempotent(): void
    {
        [$owner,$community,$board] = $this->board();
        $publishing = self::getContainer()->get(Publishing::class);
        $input = new PublishTopic('Safe deployment procedures', 'How should database changes be rolled out safely?', 'deployment-topic-key');
        $topic = $publishing->create($board, $owner, $input);
        self::assertSame($topic, $publishing->create($board, $owner, $input));
        self::assertSame(1, $this->em->getRepository(Topic::class)->count([]));
        $member = $this->member($community, 'member');
        $publishing->reply($topic, $member, 'Use backwards compatible changes and staged rollouts.');
        $message = new TopicActivity($topic->id, $member->id, $topic->version, 'reply');
        $handler = self::getContainer()->get(TopicActivityHandler::class);
        $handler($message);
        $handler($message);
        self::assertSame(1, $this->em->getRepository(Notification::class)->count(['user' => $owner]));
        self::assertSame(2, $this->em->getRepository(Post::class)->count(['topic' => $topic]));
        self::assertCount(2, self::getContainer()->get('messenger.transport.async')->getSent());
    }
    public function testWorkflowAndEditingUseVersionChecks(): void
    {
        [$owner,$community,$board] = $this->board(true);
        $member = $this->member($community, 'member');
        $publishing = self::getContainer()->get(Publishing::class);
        $topic = $publishing->create($board, $member, new PublishTopic('A proposal requiring moderation', 'Please review this operational proposal.', 'review-topic-key'));
        self::assertSame(TopicState::Pending, $topic->status);
        $publishing->transition($topic, $owner, new TransitionTopic('approve', $topic->version));
        self::assertSame(TopicState::Published, $topic->status);
        $post = $this->em->getRepository(Post::class)->findOneBy(['topic' => $topic]);
        $publishing->edit($post, $member, new EditPost('A clarified operational proposal.', 'Clarify the rollout order', 1));
        self::assertSame(2, $post->version);
        self::assertSame(1, $this->em->getRepository(PostRevision::class)->count(['post' => $post]));
        $this->expectException(ConflictHttpException::class);
        $publishing->edit($post, $member, new EditPost('A stale copy of the proposal.', 'Stale edit request', 1));
    }
    public function testPrivateBoardVisibilityAndWorkflowArchiveGuard(): void
    {
        [$owner,$community,$board] = $this->board(false, 'staff');
        $member = $this->member($community, 'member');
        $publishing = self::getContainer()->get(Publishing::class);
        $topic = $publishing->create($board, $owner, new PublishTopic('Private moderator discussion', 'Notes for the community moderation team.', 'staff-topic-key'));
        self::assertFalse(self::getContainer()->get(\App\Security\Access::class)->viewTopic($topic, $member));
        $publishing->transition($topic, $owner, new TransitionTopic('lock', $topic->version));
        $board->archivedAt = new DateTimeImmutable();
        $this->em->flush();
        $this->expectException(ConflictHttpException::class);
        $publishing->transition($topic, $owner, new TransitionTopic('unlock', $topic->version));
    }
    public function testModerationHidesReportedContentAndBlocksRestrictedMember(): void
    {
        [$owner,$community,$board] = $this->board();
        $author = $this->member($community, 'author');
        $reporter = $this->member($community, 'reporter');
        $publishing = self::getContainer()->get(Publishing::class);
        $topic = $publishing->create($board, $author, new PublishTopic('Disputed community content', 'This content needs moderator attention.', 'disputed-topic-key'));
        $post = $this->em->getRepository(Post::class)->findOneBy(['topic' => $topic]);
        $moderation = self::getContainer()->get(Moderation::class);
        $report = $moderation->report($post, $reporter, new FileReport('harassment', 'The post breaches the community harassment policy.'));
        $moderation->resolve($report, $owner, new ResolveReport('upheld', 'Evidence confirms a violation of community rules.'));
        self::assertNotNull($post->hiddenAt);
        $moderation->restrict($community, $owner, new RestrictMember($author->id, 'Repeated harassment after prior warnings.', 24));
        $this->expectException(AccessDeniedHttpException::class);
        $publishing->reply($topic, $author, 'A restricted account cannot reply.');
    }
    public function testOwnershipTransferAllowsAnonymization(): void
    {
        [$owner,$community,$board] = $this->board();
        $successor = $this->member($community, 'successor');
        self::getContainer()->get(CommunityManagement::class)->transfer($community, $owner, $successor);
        self::getContainer()->get(Privacy::class)->erase($owner, 'correct-horse-battery-staple');
        self::assertSame('Deleted member', $owner->displayName);
        self::assertSame($successor->id, $community->owner->id);
        self::assertFalse($owner->active());
        self::assertSame(0, $this->em->getRepository(Membership::class)->count(['user' => $owner]));
    }
    public function testHttpValidationCsrfAndPublishingUseTheRealKernel(): void
    {
        [$owner,$community,$board] = $this->board();
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security_main', serialize(new UsernamePasswordToken($owner, 'main', $owner->getRoles())));
        $request = Request::create('/api/session');
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $response = self::$kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $csrf = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['csrf'];
        $request = Request::create('/api/boards/'.$board->id.'/topics', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json','HTTP_ACCEPT' => 'application/json'], json_encode(['title' => 'HTTP publishing workflow','body' => 'A meaningful post submitted through the HTTP endpoint.','requestKey' => 'http-publication-key'], JSON_THROW_ON_ERROR));
        $request->setSession($session);
        $request->cookies->set($session->getName(), $session->getId());
        $denied = self::$kernel->handle($request);
        self::assertSame(403, $denied->getStatusCode());
        $request->headers->set('X-CSRF-Token', $csrf);
        $response = self::$kernel->handle($request);
        self::assertSame(201, $response->getStatusCode(), $response->getContent());
        self::assertSame('published', json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['state']);
    }
}
