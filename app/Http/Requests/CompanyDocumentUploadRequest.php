<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyDocumentUploadRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document' => 'required|file|mimes:pdf|max:10240', // Max 10MB
            'company_id' => 'required|exists:companies,id',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.required' => 'Il documento è obbligatorio.',
            'document.file' => 'Il documento deve essere un file valido.',
            'document.mimes' => 'Il documento deve essere un file PDF.',
            'document.max' => 'Il documento non può superare i 10MB.',
            'company_id.required' => 'L\'ID della company è obbligatorio.',
            'company_id.exists' => 'La company specificata non esiste.',
        ];
    }
}
