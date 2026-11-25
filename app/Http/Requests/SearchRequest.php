<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:1000'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    public function searchQuery(): string
    {
        return $this->string('query')->value();
    }

    public function limit(): int
    {
        return $this->integer('limit', 10);
    }
}
