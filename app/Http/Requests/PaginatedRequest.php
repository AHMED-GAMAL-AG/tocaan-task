<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared `page` / `per_page` handling for list endpoints.
 */
abstract class PaginatedRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 15;

    public const int MAX_PER_PAGE = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function filterRules(): array;

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
