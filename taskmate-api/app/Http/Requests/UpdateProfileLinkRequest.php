<?php

namespace App\Http\Requests;

use App\Enums\LinkPlatform;
use App\Models\ProfileLink;
use App\Rules\HttpUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileLinkRequest extends FormRequest
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
        /** @var ProfileLink $link */
        $link = $this->route('link');

        return [
            'platform' => ['sometimes', 'nullable', Rule::enum(LinkPlatform::class)],
            'label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'url' => [
                'sometimes',
                'required',
                'string',
                'max:2048',
                new HttpUrl,
                Rule::unique('profile_links', 'url')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                    ->ignore($link->id),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('url')) {
            $data['url'] = is_string($this->url) ? trim($this->url) : $this->url;
        }

        if ($this->has('label')) {
            $data['label'] = is_string($this->label) ? trim($this->label) : $this->label;
        }

        if ($this->has('platform')) {
            $data['platform'] = filled($this->platform) ? strtolower(trim((string) $this->platform)) : null;
        }

        $this->merge($data);
    }
}
