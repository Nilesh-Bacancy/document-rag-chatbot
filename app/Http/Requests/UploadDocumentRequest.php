<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only PDFs, capped at 10MB, and required.
            'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
