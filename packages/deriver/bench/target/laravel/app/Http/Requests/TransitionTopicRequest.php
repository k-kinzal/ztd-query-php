<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['action' => ['required', \Illuminate\Validation\Rule::in(['approve','lock','unlock','hide','restore','archive'])], 'version' => ['required','integer','min:1']];
    }
}
