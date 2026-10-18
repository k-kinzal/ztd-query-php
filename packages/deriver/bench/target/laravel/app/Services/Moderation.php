<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportState;
use App\Models\{Ban, Community, Post, Report, User};
use Illuminate\Support\Facades\{DB, Gate};

final class Moderation
{
    public function __construct(private readonly Audit $audit)
    {
    }
    public function report(Post $post, User $actor, array $input): Report
    {
        Gate::forUser($actor)->authorize('view', $post);
        return Report::firstOrCreate(['post_id' => $post->id, 'reporter_id' => $actor->id], $input + ['state' => ReportState::Open]);
    }
    public function resolve(Report $report, User $actor, array $input): Report
    {
        return DB::transaction(function () use ($report, $actor, $input): Report {
            $report = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('moderate', $report->post->topic);
            abort_unless($report->state === ReportState::Open, 409, 'The report was already resolved.');
            abort_if(in_array($actor->id, [$report->reporter_id, $report->post->author_id], true), 409, 'An independent moderator is required.');
            $report->update(['state' => $input['decision'], 'resolution' => $input['reason'], 'moderator_id' => $actor->id, 'resolved_at' => now()]);
            if ($report->state === ReportState::Upheld) {
                $report->post->update(['hidden_at' => now(), 'version' => $report->post->version + 1]);
            }
            $this->audit->record($actor, $report->post->topic->board->community_id, 'report.resolved', $report, ['decision' => $input['decision'], 'reason' => $input['reason']]);
            $report->reporter->notify(new \App\Notifications\ReportResolved($report));
            return $report;
        }, 3);
    }
    public function restrict(Community $community, User $actor, User $member, array $input): Ban
    {
        return DB::transaction(function () use ($community, $actor, $member, $input): Ban {
            Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $community);
            abort_unless($member->roleIn($community) === 'member', 409, 'Only ordinary members may be restricted.');
            $ban = Ban::create(['community_id' => $community->id, 'user_id' => $member->id, 'moderator_id' => $actor->id, 'reason' => $input['reason'], 'expires_at' => now()->addHours($input['hours'])]);
            $this->audit->record($actor, $community->id, 'member.restricted', $ban);
            return $ban;
        });
    }
}
