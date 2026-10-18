<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() ?? false;
    }
    public function rules(): array
    {
        return ['slug' => ['required','string','min:3','max:80','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', \Illuminate\Validation\Rule::unique('boards')->where('community_id', $this->route('community')->id)], 'name' => ['required','string','min:3','max:120'], 'description' => ['required','string','min:10','max:5000'], 'visibility' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\BoardVisibility::class)], 'requires_review' => ['boolean']];
    }
}
