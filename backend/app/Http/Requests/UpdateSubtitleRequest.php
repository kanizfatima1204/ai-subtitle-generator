<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubtitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subtitles' => [
                'required',
                'array',
                'min:1',
            ],

            'subtitles.*.id' => [
                'nullable',
                'integer',
            ],

            'subtitles.*.start' => [
                'required',
                'numeric',
                'min:0',
            ],

            'subtitles.*.end' => [
                'required',
                'numeric',
                'gt:subtitles.*.start',
            ],

            'subtitles.*.text' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }
}
