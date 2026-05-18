<?php

namespace App\Support;

class ProductSearch
{
    public static function normalizeTerm(?string $term): string
    {
        $term = trim((string) $term);

        if ($term === '') {
            return '';
        }

        return (string) preg_replace('/\s+/', ' ', $term);
    }

    public static function tokens(?string $term): array
    {
        $normalized = static::normalizeTerm($term);

        if ($normalized === '') {
            return [];
        }

        return preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function applyTokenSearch(
        $query,
        ?string $term,
        array $columns = ['products.product_name', 'products.product_code'],
        ?string $idColumn = 'products.id'
    ) {
        $normalized = static::normalizeTerm($term);
        $tokens = static::tokens($normalized);

        if ($normalized === '' || empty($tokens)) {
            return $query;
        }

        return $query->where(function ($outer) use ($tokens, $columns, $idColumn, $normalized) {
            if ($idColumn !== null && ctype_digit($normalized)) {
                $outer->orWhere($idColumn, (int) $normalized);
            }

            $outer->orWhere(function ($tokenQuery) use ($tokens, $columns) {
                foreach ($tokens as $token) {
                    $pattern = '%' . static::escapeLike($token) . '%';

                    $tokenQuery->where(function ($columnQuery) use ($columns, $pattern) {
                        foreach ($columns as $column) {
                            $columnQuery->orWhereRaw($column . " LIKE ? ESCAPE '\\\\'", [$pattern]);
                        }
                    });
                }
            });
        });
    }

    public static function orApplyTokenSearch(
        $query,
        ?string $term,
        array $columns = ['products.product_name', 'products.product_code'],
        ?string $idColumn = 'products.id'
    ) {
        $normalized = static::normalizeTerm($term);
        $tokens = static::tokens($normalized);

        if ($normalized === '' || empty($tokens)) {
            return $query;
        }

        return $query->orWhere(function ($outer) use ($tokens, $columns, $idColumn, $normalized) {
            if ($idColumn !== null && ctype_digit($normalized)) {
                $outer->orWhere($idColumn, (int) $normalized);
            }

            $outer->orWhere(function ($tokenQuery) use ($tokens, $columns) {
                foreach ($tokens as $token) {
                    $pattern = '%' . static::escapeLike($token) . '%';

                    $tokenQuery->where(function ($columnQuery) use ($columns, $pattern) {
                        foreach ($columns as $column) {
                            $columnQuery->orWhereRaw($column . " LIKE ? ESCAPE '\\\\'", [$pattern]);
                        }
                    });
                }
            });
        });
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
