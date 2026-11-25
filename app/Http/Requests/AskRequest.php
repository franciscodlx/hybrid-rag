<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:1000'],
            'evidence_limit' => ['sometimes', 'integer', 'between:1,20'],
        ];
    }

    public function question(): string
    {
        return $this->string('question')->value();
    }

    public function evidenceLimit(): int
    {
        return $this->integer('evidence_limit', 8);
    }
}
