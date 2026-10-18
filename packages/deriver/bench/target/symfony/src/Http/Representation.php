<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\{Board,Community,Post,Report,Topic};

final class Representation
{
    public function community(Community $community): array
    {
        return ['id' => $community->id,'slug' => $community->slug,'name' => $community->name,'description' => $community->description];
    }
    public function board(Board $board): array
    {
        return ['id' => $board->id,'community_id' => $board->community->id,'slug' => $board->slug,'name' => $board->name,'visibility' => $board->visibility->value,'requires_review' => $board->requiresReview];
    }
    public function topic(Topic $topic): array
    {
        return ['id' => $topic->id,'board_id' => $topic->board->id,'title' => $topic->title,'state' => $topic->status->value,'version' => $topic->version,'author' => ['id' => $topic->author->id,'name' => $topic->author->displayName],'updated_at' => $topic->updatedAt->format(DATE_ATOM)];
    }
    public function post(Post $post): array
    {
        return ['id' => $post->id,'topic_id' => $post->topic->id,'body' => $post->body,'version' => $post->version,'author' => ['id' => $post->author->id,'name' => $post->author->displayName],'created_at' => $post->createdAt->format(DATE_ATOM)];
    }
    public function report(Report $report): array
    {
        return ['id' => $report->id,'post_id' => $report->post->id,'category' => $report->category,'reason' => $report->reason,'state' => $report->status->value,'resolution' => $report->resolution];
    }
}
