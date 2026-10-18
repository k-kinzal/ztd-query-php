<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TopicState;
use App\Http\Requests\{EditPostRequest, PublishTopicRequest, ReplyRequest, TransitionTopicRequest};
use App\Http\Resources\{PostResource, TopicResource};
use App\Models\{Board, Post, Topic};
use App\Services\Publishing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DiscussionController
{
    public function index(Request $request, Board $board)
    {
        Gate::authorize('view', $board);
        $query = $board->topics()->with(['author','tags'])->whereIn('state', [TopicState::Published,TopicState::Locked,TopicState::Archived]);
        if ($request->filled('q')) {
            $input = $request->validate(['q' => 'required|string|min:2|max:100']);
            $search = '%' . addcslashes($input['q'], '%_\\') . '%';
            $query->where(fn ($query) => $query->where('title', 'like', $search)->orWhereHas('posts', fn ($posts) => $posts->whereNull('hidden_at')->where('body', 'like', $search)));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($tags) => $tags->where('slug', $request->string('tag')->toString()));
        }
        return TopicResource::collection($query->orderByDesc('last_posted_at')->orderByDesc('id')->cursorPaginate(30));
    }
    public function show(Topic $topic)
    {
        Gate::authorize('view', $topic);
        return new TopicResource($topic->load(['author','tags']));
    }
    public function posts(Topic $topic)
    {
        Gate::authorize('view', $topic);
        return PostResource::collection($topic->posts()->with(['author','attachments'])->whereNull('hidden_at')->orderBy('id')->cursorPaginate(50));
    }
    public function store(PublishTopicRequest $request, Board $board, Publishing $service)
    {
        return (new TopicResource($service->create($board, $request->user(), $request->safe()->except('request_key'), $request->validated('request_key'))->load(['author','tags'])))->response()->setStatusCode(201);
    }
    public function reply(ReplyRequest $request, Topic $topic, Publishing $service)
    {
        return (new PostResource($service->reply($topic, $request->user(), $request->validated('body'))->load('author')))->response()->setStatusCode(201);
    }
    public function edit(EditPostRequest $request, Post $post, Publishing $service)
    {
        return new PostResource($service->edit($post, $request->user(), $request->validated())->load('author'));
    }
    public function transition(TransitionTopicRequest $request, Topic $topic, Publishing $service)
    {
        return new TopicResource($service->transition($topic, $request->user(), $request->validated('action'), (int) $request->validated('version'))->load('author'));
    }
    public function revisions(Post $post)
    {
        Gate::authorize('update', $post);
        return $post->revisions()->orderByDesc('id')->cursorPaginate(30);
    }
    public function subscribe(Request $request, Topic $topic)
    {
        Gate::authorize('view', $topic);
        $topic->subscribers()->syncWithoutDetaching([$request->user()->id]);
        return response()->noContent();
    }
    public function unsubscribe(Request $request, Topic $topic)
    {
        $topic->subscribers()->detach($request->user());
        return response()->noContent();
    }
    public function react(Request $request, Post $post)
    {
        Gate::authorize('view', $post);
        abort_if($post->author_id === $request->user()->id, 409, 'You cannot react to your own post.');
        $input = $request->validate(['kind' => 'required|in:helpful,thanks,agree']);
        $post->reactions()->updateOrCreate(['user_id' => $request->user()->id], $input);
        return response()->noContent();
    }
}
