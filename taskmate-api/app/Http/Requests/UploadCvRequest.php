<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadCvRequest extends FormRequest
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
            'cv' => [
                'required',
                'file',
                'max:'.config('taskmate.cv_max_kb'),
                'mimes:'.implode(',', config('taskmate.cv_mimes')),
                'mimetypes:'.implode(',', config('taskmate.cv_mimetypes')),
            ],
        ];
    }

    /**
     * Verify the detected MIME type from the uploaded file's contents.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('cv');

                if (! $file || ! $file->isValid()) {
                    return;
                }

                $detectedMime = mime_content_type($file->getRealPath() ?: $file->getPathname());

                if ($detectedMime !== 'application/pdf') {
                    $validator->errors()->add('cv', 'The CV must be a PDF file.');
                }
            },
        ];
    }
}
