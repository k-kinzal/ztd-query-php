<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RestrictMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['user_id' => ['required','integer','exists:users,id'], 'reason' => ['required','string','min:10','max:2000'], 'hours' => ['required','integer','min:1','max:2160']];
    }
}
