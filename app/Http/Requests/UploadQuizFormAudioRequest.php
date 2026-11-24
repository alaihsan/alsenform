<?php

namespace App\Http\Requests;

use App\Services\AudioCompressor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sound for a question or a choice: recorded in the editor or an audio file of the teacher.
 */
class UploadQuizFormAudioRequest extends FormRequest
{
    /** The largest sound file, as for other media (PHP's upload_max_filesize is 40 MB). */
    public const MAX_KILOBYTES = 40960;

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
        return [
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'extensions:'.implode(',', AudioCompressor::EXTENSIONS),
                'mimetypes:'.implode(',', AudioCompressor::MIME_TYPES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih atau rekam suara terlebih dahulu.',
            'file.uploaded' => 'Suara gagal terkirim: paling besar 40 MB. Coba lagi.',
            'file.max' => 'Berkas suara terlalu besar: paling besar 40 MB.',
            'file.extensions' => 'Jenis berkas tidak didukung. Kirim MP3, M4A, WAV, OGG, WebM atau rekaman HP.',
            'file.mimetypes' => 'Berkas ini bukan berkas suara.',
        ];
    }
}
