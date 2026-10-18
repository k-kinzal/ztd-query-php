<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['decision' => ['required', \Illuminate\Validation\Rule::in(['dismissed','upheld'])], 'reason' => ['required','string','min:10','max:2000']];
    }
}
