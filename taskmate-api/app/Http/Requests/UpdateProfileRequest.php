<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'bio' => ['nullable', 'string'],
            'skills' => ['sometimes', 'array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }

        if (is_string($this->country)) {
            $this->merge(['country' => strtoupper(trim($this->country))]);
        }

        if (is_array($this->skills)) {
            $this->merge([
                'skills' => array_values(array_map(
                    fn (mixed $skill) => is_string($skill) ? trim($skill) : $skill,
                    $this->skills,
                )),
            ]);
        }
    }
}
