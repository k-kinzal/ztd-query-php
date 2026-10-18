<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EditPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['body' => ['required','string','min:2','max:50000'], 'reason' => ['required','string','min:3','max:500'], 'version' => ['required','integer','min:1']];
    }
}
