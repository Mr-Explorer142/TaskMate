<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteMessageRequest extends FormRequest
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
            'scope' => ['required', 'string', Rule::in(['me', 'everyone'])],
        ];
    }

    /**
     * Normalize the deletion scope before validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('scope')) {
            return;
        }

        $this->merge([
            'scope' => is_string($this->scope) ? strtolower(trim($this->scope)) : $this->scope,
        ]);
    }
}
