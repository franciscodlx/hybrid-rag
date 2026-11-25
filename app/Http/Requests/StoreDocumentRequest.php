<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StoreDocumentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $mimetypes */
        $mimetypes = config('rag.uploads.mimetypes');

        return [
            'file' => ['required', 'file', 'max:'.config('rag.uploads.max_kilobytes'), 'mimetypes:'.implode(',', $mimetypes)],
            'title' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function document(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }

    public function title(): string
    {
        $title = $this->string('title')->trim()->value();

        return $title !== '' ? $title : pathinfo($this->document()->getClientOriginalName(), PATHINFO_FILENAME);
    }
}
