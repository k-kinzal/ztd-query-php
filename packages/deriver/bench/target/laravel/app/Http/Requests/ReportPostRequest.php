<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReportPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['category' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ReportCategory::class)], 'reason' => ['required','string','min:10','max:2000']];
    }
}
