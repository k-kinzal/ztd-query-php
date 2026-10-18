<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\{CreateBoardRequest, CreateCommunityRequest};
use App\Models\{Community, User};
use App\Services\CommunityManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CommunityController
{
    public function index()
    {
        return Community::select(['id','slug','name','description'])->orderBy('id')->cursorPaginate(30);
    }
    public function store(CreateCommunityRequest $request, CommunityManagement $service)
    {
        return response()->json($service->create($request->user(), $request->validated()), 201);
    }
    public function boards(Request $request, Community $community)
    {
        return $community->boards()->visibleTo($request->user())->orderBy('id')->cursorPaginate(30);
    }
    public function createBoard(CreateBoardRequest $request, Community $community, CommunityManagement $service)
    {
        return response()->json($service->board($community, $request->user(), $request->validated()), 201);
    }
    public function join(Request $request, Community $community)
    {
        $community->members()->syncWithoutDetaching([$request->user()->id => ['role' => $request->user()->roleIn($community) ?? 'member']]);
        return response()->json(['joined' => true]);
    }
    public function members(Request $request, Community $community)
    {
        Gate::authorize('update', $community);
        return $community->members()->select(['users.id','name'])->orderBy('users.id')->paginate(50);
    }
    public function role(Request $request, Community $community, User $user, CommunityManagement $service)
    {
        $input = $request->validate(['role' => ['required',Rule::in(['member','moderator'])]]);
        $service->role($community, $request->user(), $user, $input['role']);
        return response()->noContent();
    }
    public function transfer(Request $request, Community $community, CommunityManagement $service)
    {
        $input = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $service->transfer($community, $request->user(), User::findOrFail($input['user_id']));
        return response()->noContent();
    }
    public function audit(Community $community)
    {
        Gate::authorize('update', $community);
        return \App\Models\AuditEntry::where('community_id', $community->id)->orderByDesc('id')->cursorPaginate(50);
    }
    public function archiveBoard(Request $request, \App\Models\Board $board)
    {
        Gate::authorize('update', $board);
        $input = $request->validate(['archived' => 'required|boolean']);
        $board->update(['archived_at' => $input['archived'] ? now() : null]);
        app(\App\Services\Audit::class)->record($request->user(), $board->community_id, $input['archived'] ? 'board.archived' : 'board.reopened', $board);
        return response()->noContent();
    }

}
