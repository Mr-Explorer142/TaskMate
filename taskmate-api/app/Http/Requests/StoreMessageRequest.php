<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Normalize the message body before validation.
     */
    protected function prepareForValidation(): void
    {
        if (! is_string($this->body)) {
            return;
        }

        $body = str_replace("\0", '', $this->body);
        $body = preg_replace("/\r\n|\r/", "\n", $body) ?? $body;

        $this->merge(['body' => trim($body)]);
    }
}
