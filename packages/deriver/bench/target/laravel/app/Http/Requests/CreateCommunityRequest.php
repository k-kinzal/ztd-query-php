<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['slug' => ['required','string','min:3','max:80','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/','unique:communities,slug'], 'name' => ['required','string','min:3','max:120'], 'description' => ['required','string','min:10','max:5000']];
    }
}
