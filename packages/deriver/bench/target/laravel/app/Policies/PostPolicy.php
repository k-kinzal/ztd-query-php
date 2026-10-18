<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\{Post, User};

final class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        return $post->hidden_at === null && (new TopicPolicy())->view($user, $post->topic);
    }
    public function update(User $user, Post $post): bool
    {
        return $this->view($user, $post) && ((new BoardPolicy())->publish($user, $post->topic->board))
            && ($user->moderates($post->topic->board->community) || ($post->author_id === $user->id && $post->created_at->gt(now()->subMinutes(30)) && $post->topic->state === \App\Enums\TopicState::Published));
    }
}
