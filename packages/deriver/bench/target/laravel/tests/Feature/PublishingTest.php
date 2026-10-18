<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DeliverDiscussionEvent;
use App\Models\{OutboxEvent, Post, Topic, User};
use App\Services\{CommunityManagement, Publishing};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PublishingTest extends TestCase
{
    use RefreshDatabase;

    private function board(bool $review = false, string $visibility = 'public'): array
    {
        Queue::fake();
        $owner = User::factory()->create();
        $community = app(CommunityManagement::class)->create($owner, ['slug' => 'engineering','name' => 'Engineering','description' => 'Engineering support community']);
        $board = app(CommunityManagement::class)->board($community, $owner, ['slug' => 'support','name' => 'Support','description' => 'Operational engineering support','visibility' => $visibility,'requires_review' => $review]);
        return [$owner, $community, $board];
    }

    public function testPublicationIsIdempotentAndRepliesProduceDurableNotifications(): void
    {
        [$owner, $community, $board] = $this->board();
        Sanctum::actingAs($owner);
        $input = ['title' => 'Safe deployment procedures','body' => 'How should we roll out schema changes safely?','tags' => ['Deployment','Database']];
        $response = $this->withHeader('Idempotency-Key', 'deployment-question')->postJson('/api/boards/' . $board->id . '/topics', $input)->assertCreated();
        $id = $response->json('data.id');
        $this->withHeader('Idempotency-Key', 'deployment-question')->postJson('/api/boards/' . $board->id . '/topics', $input)->assertCreated()->assertJsonPath('data.id', $id);
        self::assertSame(1, Topic::count());
        $member = User::factory()->create();
        $community->members()->attach($member, ['role' => 'member']);
        Sanctum::actingAs($member);
        $this->postJson('/api/topics/' . $id . '/replies', ['body' => 'Deploy backwards compatible changes first.'])->assertCreated();
        $event = OutboxEvent::where('type', 'replied')->firstOrFail();
        (new DeliverDiscussionEvent($event->id))->handle();
        (new DeliverDiscussionEvent($event->id))->handle();
        self::assertSame(1, $owner->notifications()->count());
        self::assertNotNull($event->fresh()->delivered_at);
        Queue::assertPushed(DeliverDiscussionEvent::class);
        $this->getJson('/api/topics/' . $id . '/posts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function testReviewWorkflowAndStaleEditsAreEnforced(): void
    {
        [$owner, $community, $board] = $this->board(true);
        $member = User::factory()->create();
        $community->members()->attach($member, ['role' => 'member']);
        Sanctum::actingAs($member);
        $response = $this->withHeader('Idempotency-Key', 'review-required-topic')->postJson('/api/boards/' . $board->id . '/topics', ['title' => 'A proposed operating procedure','body' => 'Please review this new operating procedure.'])->assertCreated()->assertJsonPath('data.state', 'pending');
        $topic = $response->json('data.id');
        $this->postJson('/api/topics/' . $topic . '/replies', ['body' => 'Not approved yet'])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->postJson('/api/topics/' . $topic . '/transition', ['action' => 'approve','version' => 1])->assertOk()->assertJsonPath('data.state', 'published');
        Sanctum::actingAs($member);
        $post = Post::where('topic_id', $topic)->firstOrFail();
        $this->putJson('/api/posts/' . $post->id, ['body' => 'Revised operating procedure','reason' => 'Clarify deployment order','version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson('/api/posts/' . $post->id, ['body' => 'Stale operating procedure','reason' => 'Outdated edit','version' => 1])->assertConflict();
        self::assertSame(1, $post->revisions()->count());
    }

    public function testPrivateBoardAndStaffBoardDoNotLeakToOtherMembers(): void
    {
        [$owner, $community, $board] = $this->board(false, 'staff');
        $topic = app(Publishing::class)->create($board, $owner, ['title' => 'Confidential moderation discussion','body' => 'Private moderator notes and review.'], 'staff-topic-key');
        $member = User::factory()->create();
        $community->members()->attach($member, ['role' => 'member']);
        Sanctum::actingAs($member);
        $this->getJson('/api/boards/' . $board->id . '/topics')->assertForbidden();
        $this->getJson('/api/topics/' . $topic->id)->assertForbidden();
        $this->getJson('/api/communities/' . $community->id . '/boards')->assertOk()->assertJsonCount(0, 'data');
    }

    public function testRestrictionStopsPostingAndResolvedReportHidesContent(): void
    {
        [$owner, $community, $board] = $this->board();
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $community->members()->attach($author, ['role' => 'member']);
        $community->members()->attach($reporter, ['role' => 'member']);
        $topic = app(Publishing::class)->create($board, $author, ['title' => 'A disputed community discussion','body' => 'This content needs review by moderators.'], 'disputed-topic-key');
        $post = $topic->posts()->firstOrFail();
        Sanctum::actingAs($reporter);
        $report = $this->postJson('/api/posts/' . $post->id . '/reports', ['category' => 'harassment','reason' => 'This content violates the harassment policy.'])->assertCreated()->json('id');
        $this->putJson('/api/reports/' . $report, ['decision' => 'upheld','reason' => 'The content violates the community rules.'])->assertForbidden();
        Sanctum::actingAs($owner);
        $this->putJson('/api/reports/' . $report, ['decision' => 'upheld','reason' => 'The content violates the community rules.'])->assertOk();
        $this->postJson('/api/communities/' . $community->id . '/restrictions', ['user_id' => $author->id,'reason' => 'Repeated harassment despite prior warnings.','hours' => 24])->assertCreated();
        Sanctum::actingAs($author);
        $this->postJson('/api/topics/' . $topic->id . '/replies', ['body' => 'A restricted account reply'])->assertForbidden();
        $this->getJson('/api/topics/' . $topic->id . '/posts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function testOwnershipTransferAndPrivacyErasure(): void
    {
        [$owner, $community, $board] = $this->board();
        $successor = User::factory()->create();
        $community->members()->attach($successor, ['role' => 'member']);
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/account', ['password' => 'password'])->assertConflict();
        $this->postJson('/api/communities/' . $community->id . '/transfer', ['user_id' => $successor->id])->assertNoContent();
        $this->deleteJson('/api/account', ['password' => 'password'])->assertNoContent();
        self::assertSame('Deleted member', $owner->fresh()->name);
        self::assertSame($successor->id, $community->fresh()->owner_id);
        self::assertSame('owner', $successor->roleIn($community->fresh()));
    }
    public function testBearerTokenWorksOnReadablePrivateBoardRoutes(): void
    {
        [$owner, $community, $board] = $this->board(false, 'members');
        $member = User::factory()->create();
        $community->members()->attach($member, ['role' => 'member']);
        $token = $member->createToken('browser-client')->plainTextToken;
        $this->withToken($token)->getJson('/api/communities/' . $community->id . '/boards')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/boards/' . $board->id . '/topics')->assertOk();
    }

    public function testArchivedBoardRejectsPublicationUntilReopened(): void
    {
        [$owner, $community, $board] = $this->board();
        Sanctum::actingAs($owner);
        $this->putJson('/api/boards/' . $board->id . '/archive', ['archived' => true])->assertNoContent();
        $input = ['title' => 'A new community discussion', 'body' => 'This request must respect the archive state.'];
        $this->withHeader('Idempotency-Key', 'archived-board-topic')->postJson('/api/boards/' . $board->id . '/topics', $input)->assertForbidden();
        $this->putJson('/api/boards/' . $board->id . '/archive', ['archived' => false])->assertNoContent();
        $this->withHeader('Idempotency-Key', 'archived-board-topic')->postJson('/api/boards/' . $board->id . '/topics', $input)->assertCreated();
    }

}
