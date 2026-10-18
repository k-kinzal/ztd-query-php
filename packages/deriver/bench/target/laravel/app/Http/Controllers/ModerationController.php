<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\{ReportPostRequest, ResolveReportRequest, RestrictMemberRequest};
use App\Models\{Ban, Community, Post, Report, User};
use App\Services\Moderation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ModerationController
{
    public function queue(Community $community)
    {
        Gate::authorize('update', $community);
        return Report::with('post')->where('state', 'open')->whereHas('post.topic.board', fn ($boards) => $boards->where('community_id', $community->id))->orderBy('id')->cursorPaginate(50);
    }
    public function report(ReportPostRequest $request, Post $post, Moderation $service)
    {
        return response()->json($service->report($post, $request->user(), $request->validated()), 201);
    }
    public function resolve(ResolveReportRequest $request, Report $report, Moderation $service)
    {
        return $service->resolve($report, $request->user(), $request->validated());
    }
    public function restrict(RestrictMemberRequest $request, Community $community, Moderation $service)
    {
        return response()->json($service->restrict($community, $request->user(), User::findOrFail($request->validated('user_id')), $request->validated()), 201);
    }
    public function revoke(Request $request, Ban $ban)
    {
        Gate::authorize('update', $ban->community);
        $ban->update(['revoked_at' => now()]);
        app(\App\Services\Audit::class)->record($request->user(), $ban->community_id, 'restriction.revoked', $ban);
        return response()->noContent();
    }
}
