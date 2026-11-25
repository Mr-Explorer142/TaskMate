<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize free-form input before validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->exists('comment') || ! is_string($this->input('comment'))) {
            return;
        }

        $comment = trim((string) $this->input('comment'));

        $this->merge([
            'comment' => $comment === '' ? null : $comment,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reviewee_id' => ['required', 'integer', 'exists:users,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
