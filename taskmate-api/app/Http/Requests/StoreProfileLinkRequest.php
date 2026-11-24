<?php

namespace App\Http\Requests;

use App\Enums\LinkPlatform;
use App\Rules\HttpUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['nullable', Rule::enum(LinkPlatform::class)],
            'label' => ['nullable', 'string', 'max:60'],
            'url' => [
                'required',
                'string',
                'max:2048',
                new HttpUrl,
                Rule::unique('profile_links', 'url')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => is_string($this->url) ? trim($this->url) : $this->url,
            'label' => is_string($this->label) ? trim($this->label) : $this->label,
            'platform' => filled($this->platform) ? strtolower(trim((string) $this->platform)) : null,
        ]);
    }
}
