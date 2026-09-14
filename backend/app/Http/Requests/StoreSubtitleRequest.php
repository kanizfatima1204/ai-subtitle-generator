<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubtitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:512000',
                'mimes:mp4,mov,avi,mkv,webm,mp3,wav,m4a,aac',
            ],

            'language' => [
                'nullable',
                'string',
                'in:auto,bn,en,mixed',
            ],

            'model' => [
                'nullable',
                'string',
                'in:small,medium,large-v3',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a video or audio file.',
            'file.mimes' => 'Supported formats: MP4, MOV, AVI, MKV, WEBM, MP3, WAV, M4A and AAC.',
            'file.max' => 'The maximum file size is 500 MB.',
        ];
    }
}
