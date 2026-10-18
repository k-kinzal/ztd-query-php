<?php

declare(strict_types=1);

namespace Harbor\Tests;

use Harbor\Application;
use Harbor\Database\{Connection, Migrator};
use Harbor\Http\Request;
use Harbor\Notification\Inbox;
use Harbor\Security\{Access, Session};
use PDO;
use PHPUnit\Framework\TestCase;

final class ForumTest extends TestCase
{
    private Connection $database;
    private Application $application;
    private Session $session;

    protected function setUp(): void
    {
        $this->database = new Connection(new PDO('sqlite::memory:'));
        (new Migrator($this->database))->migrate(dirname(__DIR__) . '/database');
        $data = [];
        $this->session = new Session($data);
        $this->application = new Application($this->database, $this->session, dirname(__DIR__) . '/templates');
    }

    private function request(string $method, string $path, array $body = [], int $status = 200, array $headers = []): array
    {
        $response = $this->application->handle(new Request($method, $path, [], $body, $headers + ['x-csrf-token' => $this->session->csrf()]));
        self::assertSame($status, $response->status, $response->body);
        return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function register(string $name): int
    {
        return $this->request('POST', '/api/register', ['name' => $name, 'email' => $name . '@example.test', 'password' => 'correct-horse-battery-staple'], 201)['id'];
    }

    private function community(bool $review = false, string $visibility = 'public'): array
    {
        $owner = $this->register('owner');
        $community = $this->request('POST', '/api/communities', ['slug' => 'engineering', 'name' => 'Engineering', 'description' => 'Engineering discussion and support'], 201)['id'];
        $board = $this->request('POST', "/api/communities/$community/boards", ['slug' => 'support', 'title' => 'Support board', 'visibility' => $visibility, 'requires_review' => $review], 201)['id'];
        return [$owner, $community, $board];
    }

    public function testPublishingRepliesAndIdempotentNotificationDelivery(): void
    {
        [$owner, $community, $board] = $this->community();
        $input = ['title' => 'How do we deploy safely?', 'body' => 'We need a repeatable deployment procedure.'];
        $topic = $this->request('POST', "/api/boards/$board/topics", $input, 201, ['idempotency-key' => 'deployment-topic'])['id'];
        self::assertSame($topic, $this->request('POST', "/api/boards/$board/topics", $input, 201, ['idempotency-key' => 'deployment-topic'])['id']);
        $member = $this->register('member');
        $this->request('POST', "/api/communities/$community/join");
        $this->request('POST', "/api/topics/$topic/replies", ['body' => 'Use staged rollouts with health checks.'], 201);
        $inbox = new Inbox($this->database, new Access($this->database));
        self::assertSame(1, $inbox->deliver());
        self::assertSame(0, $inbox->deliver());
        $this->session->login($owner);
        self::assertCount(1, $this->request('GET', '/api/notifications'));
        self::assertCount(2, $this->request('GET', "/api/topics/$topic")['posts']);
        self::assertSame($member, $this->database->one('SELECT author_id FROM posts ORDER BY id DESC LIMIT 1')['author_id']);
    }

    public function testApprovalAndOptimisticEditing(): void
    {
        [$owner, $community, $board] = $this->community(true);
        $member = $this->register('member');
        $this->request('POST', "/api/communities/$community/join");
        $topic = $this->request('POST', "/api/boards/$board/topics", ['title' => 'Please review this proposal', 'body' => 'This proposal changes the deployment process.'], 201, ['idempotency-key' => 'review-topic'])['id'];
        self::assertSame('pending', $this->request('GET', "/api/topics/$topic")['state']);
        $this->request('POST', "/api/topics/$topic/replies", ['body' => 'A reply before approval'], 409);
        $this->session->login($owner);
        $this->request('POST', "/api/topics/$topic/transition", ['action' => 'approve', 'version' => 1]);
        $this->session->login($member);
        $post = $this->request('GET', "/api/topics/$topic")['posts'][0]['id'];
        $this->request('PUT', "/api/posts/$post", ['body' => 'The revised deployment process.', 'reason' => 'Clarify the process', 'version' => 1]);
        $this->request('PUT', "/api/posts/$post", ['body' => 'A stale edit must not replace the new text.', 'reason' => 'Old client state', 'version' => 1], 409);
        self::assertCount(1, $this->database->rows('SELECT * FROM revisions'));
        self::assertSame('The revised deployment process.', $this->request('GET', "/api/topics/$topic")['posts'][0]['body']);
    }

    public function testPrivateBoardCannotBeReadOrSearchedByOutsider(): void
    {
        [$owner, $community, $board] = $this->community(false, 'members');
        $topic = $this->request('POST', "/api/boards/$board/topics", ['title' => 'Internal operating procedures', 'body' => 'Only members should see this discussion.'], 201, ['idempotency-key' => 'private-topic'])['id'];
        $this->register('outsider');
        $this->request('GET', "/api/boards/$board/topics", [], 404);
        self::assertSame(404, $this->application->handle(new Request('GET', "/api/boards/$board/search", ['q' => 'Internal']))->status);
        $this->request('GET', "/api/topics/$topic", [], 404);
        self::assertSame([], $this->request('GET', "/api/communities/$community/boards"));
    }

    public function testReportResolutionRequiresIndependentModeratorAndHidesPost(): void
    {
        [$owner, $community, $board] = $this->community();
        $author = $this->register('author');
        $this->request('POST', "/api/communities/$community/join");
        $topic = $this->request('POST', "/api/boards/$board/topics", ['title' => 'A disputed community post', 'body' => 'This post contains disputed community content.'], 201, ['idempotency-key' => 'disputed-topic'])['id'];
        $post = $this->request('GET', "/api/topics/$topic")['posts'][0]['id'];
        $reporter = $this->register('reporter');
        $report = $this->request('POST', "/api/posts/$post/reports", ['reason' => 'This content violates the community rules.'], 201)['id'];
        $this->request('PUT', "/api/reports/$report", ['decision' => 'upheld', 'reason' => 'The content breaches the harassment policy.'], 403);
        $this->session->login($owner);
        $this->request('PUT', "/api/reports/$report", ['decision' => 'upheld', 'reason' => 'The content breaches the harassment policy.']);
        self::assertSame([], $this->request('GET', "/api/topics/$topic")['posts']);
        $this->request('POST', "/api/communities/$community/sanctions", ['user' => $author, 'reason' => 'Repeated harassment after prior warnings.', 'hours' => 24], 201);
        $this->session->login($author);
        $this->request('POST', "/api/topics/$topic/replies", ['body' => 'Attempt to bypass a posting restriction'], 403);
    }

    public function testCsrfAndIdempotencyConflictsDoNotWrite(): void
    {
        [$owner, $community, $board] = $this->community();
        $this->request('POST', "/api/boards/$board/topics", ['title' => 'A valid community topic', 'body' => 'A valid initial community topic body.'], 403, ['x-csrf-token' => 'invalid', 'idempotency-key' => 'csrf-topic']);
        self::assertSame([], $this->database->rows('SELECT * FROM topics'));
        $this->request('POST', "/api/boards/$board/topics", ['title' => 'A valid community topic', 'body' => 'A valid initial community topic body.'], 201, ['idempotency-key' => 'same-topic']);
        $this->request('POST', "/api/boards/$board/topics", ['title' => 'Different content with same key', 'body' => 'This must never overwrite the original topic.'], 409, ['idempotency-key' => 'same-topic']);
        self::assertCount(1, $this->database->rows('SELECT * FROM topics'));
    }

    public function testPrivacyErasureRemovesContentAndMembership(): void
    {
        [$owner, $community, $board] = $this->community();
        $member = $this->register('member');
        $this->request('POST', "/api/communities/$community/join");
        $this->request('POST', "/api/boards/$board/topics", ['title' => 'My personal community topic', 'body' => 'Content that will be removed by its author.'], 201, ['idempotency-key' => 'erase-topic']);
        self::assertArrayNotHasKey('password', $this->request('GET', '/api/account/export')['account']);
        $this->request('DELETE', '/api/account', ['password' => 'correct-horse-battery-staple']);
        self::assertSame('erased', $this->database->one('SELECT status FROM users WHERE id = ?', [$member])['status']);
        self::assertSame([], $this->database->rows('SELECT * FROM memberships WHERE user_id = ?', [$member]));
        self::assertSame('[removed by author]', $this->database->one('SELECT body FROM posts')['body']);
        self::assertNull($this->session->user());
    }
    public function testOwnerCanTransferBeforeErasingTheirAccount(): void
    {
        [$owner, $community, $board] = $this->community();
        $successor = $this->register('successor');
        $this->request('POST', "/api/communities/$community/join");
        $this->session->login($owner);
        $this->request('DELETE', '/api/account', ['password' => 'correct-horse-battery-staple'], 409);
        $this->request('POST', "/api/communities/$community/transfer", ['user' => $successor]);
        $this->request('DELETE', '/api/account', ['password' => 'correct-horse-battery-staple']);
        self::assertSame($successor, $this->database->one('SELECT owner_id FROM communities WHERE id = ?', [$community])['owner_id']);
        self::assertSame('owner', $this->database->one('SELECT role FROM memberships WHERE community_id = ? AND user_id = ?', [$community, $successor])['role']);
    }

    public function testArchivedBoardRejectsPublicationUntilReopened(): void
    {
        [$owner, $community, $board] = $this->community();
        $this->request('PUT', "/api/boards/$board/archive", ['archived' => true]);
        $input = ['title' => 'A new community discussion', 'body' => 'This request must respect the archive state.'];
        $this->request('POST', "/api/boards/$board/topics", $input, 403, ['idempotency-key' => 'archived-board-topic']);
        $this->request('PUT', "/api/boards/$board/archive", ['archived' => false]);
        $this->request('POST', "/api/boards/$board/topics", $input, 201, ['idempotency-key' => 'archived-board-topic']);
    }

}
