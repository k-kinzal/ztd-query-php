<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Privacy;
use Illuminate\Http\Request;

final class AccountController
{
    public function notifications(Request $request)
    {
        return $request->user()->notifications()->orderByDesc('id')->cursorPaginate(50);
    }
    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();
        return response()->noContent();
    }
    public function export(Request $request, Privacy $privacy)
    {
        return response()->json($privacy->export($request->user()));
    }
    public function erase(Request $request, Privacy $privacy)
    {
        $input = $request->validate(['password' => 'required|string|max:200']);
        $privacy->erase($request->user(), $input['password']);
        return response()->noContent();
    }
}
