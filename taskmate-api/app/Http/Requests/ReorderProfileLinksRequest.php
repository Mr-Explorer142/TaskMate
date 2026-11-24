<?php

namespace App\Http\Requests;

use App\Models\ProfileLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderProfileLinksRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! is_array($this->input('ids'))) {
                    return;
                }

                $ids = collect($this->input('ids'))->map(fn (mixed $id) => (int) $id)->unique();

                $ownedCount = ProfileLink::query()
                    ->where('user_id', $this->user()->id)
                    ->whereIn('id', $ids)
                    ->count();

                if ($ownedCount !== $ids->count()) {
                    $validator->errors()->add('ids', 'Every link must belong to your profile.');
                }
            },
        ];
    }
}
