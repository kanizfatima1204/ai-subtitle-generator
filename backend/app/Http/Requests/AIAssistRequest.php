<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AIAssistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text' => [
                'required',
                'string',
                'max:5000',
            ],

            'action' => [
                'required',
                'string',
                'in:improve,translate,grammar,simplify',
            ],

            'target_language' => [
                'nullable',
                'string',
                'max:10',
            ],
        ];
    }
}
