<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublishTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    protected function prepareForValidation(): void
    {
        $this->merge(['request_key' => $this->header('Idempotency-Key')]);
    }
    public function rules(): array
    {
        return ['title' => ['required','string','min:5','max:200'], 'body' => ['required','string','min:10','max:50000'], 'tags' => ['array','max:5'], 'tags.*' => ['string','min:2','max:60','distinct','regex:/^[a-zA-Z0-9][a-zA-Z0-9 -]*$/'], 'request_key' => ['required','string','min:8','max:128']];
    }
}
