<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['attachment' => ['required','file','max:10240','mimes:pdf,png,jpg,jpeg,txt']];
    }
}
