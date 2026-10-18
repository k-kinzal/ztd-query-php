<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Board, OutboxEvent, Post, PublicationReceipt, Tag, Topic, User};
use DomainException;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;

final class Publishing
{
    public function __construct(private readonly Audit $audit, private readonly PublishingRules $rules)
    {
    }

    public function create(Board $board, User $actor, array $input, string $key): Topic
    {
        return DB::transaction(function () use ($board, $actor, $input, $key): Topic {
            // Lock the account to serialize duplicate request keys even before a receipt exists.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $board = Board::whereKey($board->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('publish', $board);
            $fingerprint = hash('sha256', json_encode([$board->id, $input], JSON_THROW_ON_ERROR));
            $receipt = PublicationReceipt::where('user_id', $actor->id)->where('key', $key)->first();
            if ($receipt !== null) {
                abort_unless(hash_equals($receipt->fingerprint, $fingerprint), 409, 'The request key belongs to different content.');
                return $receipt->topic;
            }
            $topic = $board->topics()->create(['author_id' => $actor->id, 'title' => $input['title'], 'state' => $this->rules->initialState($board->requires_review, $actor->roleIn($board->community)), 'last_posted_at' => now()]);
            $topic->posts()->create(['author_id' => $actor->id, 'body' => $input['body']]);
            $tagIds = [];
            foreach ($input['tags'] ?? [] as $name) {
                $tagIds[] = Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
            }
            $topic->tags()->sync($tagIds);
            $topic->subscribers()->attach($actor);
            PublicationReceipt::create(['user_id' => $actor->id, 'key' => $key, 'fingerprint' => $fingerprint, 'topic_id' => $topic->id]);
            $this->audit->record($actor, $board->community_id, 'topic.created', $topic, ['state' => $topic->state->value]);
            $this->event($topic, $actor, 'created');
            return $topic;
        }, 3);
    }

    public function reply(Topic $topic, User $actor, string $body): Post
    {
        return DB::transaction(function () use ($topic, $actor, $body): Post {
            $topic = Topic::whereKey($topic->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('reply', $topic);
            $post = $topic->posts()->create(['author_id' => $actor->id, 'body' => $body]);
            $topic->update(['last_posted_at' => now(), 'version' => $topic->version + 1]);
            $topic->subscribers()->syncWithoutDetaching([$actor->id]);
            $this->event($topic, $actor, 'replied');
            return $post;
        }, 3);
    }

    public function edit(Post $post, User $actor, array $input): Post
    {
        return DB::transaction(function () use ($post, $actor, $input): Post {
            $post = Post::whereKey($post->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $post);
            abort_unless($post->version === $input['version'], 409, 'The post has changed.');
            $post->revisions()->create(['editor_id' => $actor->id, 'body' => $post->body, 'reason' => $input['reason']]);
            $post->update(['body' => $input['body'], 'version' => $post->version + 1]);
            $this->audit->record($actor, $post->topic->board->community_id, 'post.edited', $post, ['reason' => $input['reason']]);
            return $post;
        }, 3);
    }

    public function transition(Topic $topic, User $actor, string $action, int $version): Topic
    {
        return DB::transaction(function () use ($topic, $actor, $action, $version): Topic {
            $topic = Topic::whereKey($topic->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('moderate', $topic);
            abort_unless($topic->version === $version, 409, 'The topic has changed.');
            try {
                $next = $this->rules->nextState($topic->state->value, $action);
            } catch (DomainException $error) {
                abort(409, $error->getMessage());
            }
            $topic->update(['state' => $next, 'version' => $topic->version + 1]);
            $this->audit->record($actor, $topic->board->community_id, 'topic.' . $action, $topic);
            $this->event($topic, $actor, $action);
            return $topic;
        }, 3);
    }

    private function event(Topic $topic, User $actor, string $type): void
    {
        $event = OutboxEvent::create(['topic_id' => $topic->id, 'actor_id' => $actor->id, 'type' => $type, 'payload' => ['title' => $topic->title]]);
        \App\Jobs\DeliverDiscussionEvent::dispatch($event->id)->afterCommit();
    }
}
