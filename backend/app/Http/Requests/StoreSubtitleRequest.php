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
                'mimes:mp4,mov,avi,mkv,webm,mp3,wav,m4a,aac,flac,ogg',
            ],

            'language' => [
                'nullable',
                'string',
                'in:auto,en,zh,de,es,ru,ko,fr,ja,pt,tr,pl,ca,nl,ar,sv,it,id,hi,fi,vi,he,uk,el,ms,cs,ro,da,hu,ta,no,th,ur,hr,bg,lt,la,mi,ml,cy,sk,te,fa,lv,bn,sr,az,sl,et,mk,ne,be,sw,yo,gu,pa,mr,kn,as,ks,or,ps',
            ],

            'model' => [
                'nullable',
                'string',
                'in:tiny,base,small,medium,large,large-v2,large-v3',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a video or audio file.',
            'file.mimes' => 'Supported formats: MP4, MOV, AVI, MKV, WEBM, MP3, WAV, M4A, AAC, FLAC and OGG.',
            'file.max' => 'The maximum file size is 500 MB.',
        ];
    }
}
