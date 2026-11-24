<?php

namespace App\Http\Requests;

use App\Enums\TaskType;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Task|null $task */
        $task = $this->route('task');
        $type = $this->input('type', $task?->type?->value);

        return [
            'title' => ['sometimes', 'required', 'string', 'min:5', 'max:120'],
            'description' => ['sometimes', 'required', 'string', 'min:20', 'max:5000'],
            'team_size' => ['sometimes', 'required', 'integer', 'min:1', 'max:20'],
            'type' => ['sometimes', 'required', Rule::enum(TaskType::class)],
            'reward' => ['sometimes', 'required', ...$this->rewardRules($type)],
            'deadline' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'skills' => ['sometimes', 'nullable', 'array', 'max:15'],
            'skills.*' => ['string', 'min:2', 'max:40'],
        ];
    }

    /**
     * Build the reward rules for the given task type.
     *
     * @return array<int, mixed>
     */
    private function rewardRules(mixed $type): array
    {
        if ($type === TaskType::Points->value) {
            return ['integer', 'min:1'];
        }

        return ['numeric', 'gt:0', 'max:1000000', 'decimal:0,2'];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->title)) {
            $this->merge(['title' => trim($this->title)]);
        }

        if (is_string($this->description)) {
            $this->merge(['description' => trim($this->description)]);
        }

        if (is_array($this->skills)) {
            $this->merge(['skills' => $this->normalizeSkills($this->skills)]);
        }
    }

    /**
     * Trim skills and collapse case-insensitive duplicates.
     *
     * @param  array<int, mixed>  $skills
     * @return array<int, mixed>
     */
    private function normalizeSkills(array $skills): array
    {
        $seen = [];
        $normalized = [];

        foreach ($skills as $skill) {
            if ($skill === null) {
                continue;
            }

            if (! is_string($skill)) {
                $normalized[] = $skill;

                continue;
            }

            $trimmed = trim($skill);

            if ($trimmed === '') {
                continue;
            }

            $key = mb_strtolower($trimmed);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $trimmed;
        }

        return array_values($normalized);
    }
}
