<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Community, PostRevision, User};
use Illuminate\Support\Facades\{DB, Hash};

final class Privacy
{
    public function export(User $user): array
    {
        return ['account' => $user->only(['id','name','email','created_at']), 'memberships' => $user->communities()->get(['communities.id','name']), 'posts' => $user->posts()->get(['id','topic_id','body','created_at']), 'reports' => \App\Models\Report::where('reporter_id', $user->id)->get(['id','reason','state','resolution'])];
    }
    public function erase(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($password, $user->password), 403, 'The current password is required.');
            abort_if(Community::where('owner_id', $user->id)->exists(), 409, 'Transfer your communities before deleting your account.');
            PostRevision::whereIn('post_id', $user->posts()->select('id'))->delete();
            \App\Models\Attachment::where('user_id', $user->id)->update(['purge_requested_at' => now()]);
            \App\Jobs\PurgeAttachments::dispatch()->afterCommit();
            $user->topics()->update(['title' => '[removed by author]']);
            $user->posts()->update(['body' => '[removed by author]', 'hidden_at' => now()]);
            \App\Models\Report::where('reporter_id', $user->id)->update(['reason' => '[removed by reporter]']);
            $user->communities()->detach();
            DB::table('subscriptions')->where('user_id', $user->id)->delete();
            DB::table('reactions')->where('user_id', $user->id)->delete();
            $user->notifications()->delete();
            $user->tokens()->delete();
            $user->forceFill(['name' => 'Deleted member', 'email' => 'erased-' . $user->id . '@invalid.local', 'password' => bin2hex(random_bytes(64)), 'email_verified_at' => null, 'remember_token' => null, 'suspended_until' => now()->addYears(100)])->save();
        });
    }
}
