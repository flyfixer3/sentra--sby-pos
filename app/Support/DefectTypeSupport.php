<?php

namespace App\Support;

class DefectTypeSupport
{
    public static function normalizeList($raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/\s*,\s*/', $raw) ?: [];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $value) {
            $label = trim((string) $value);
            if ($label === '') {
                continue;
            }

            $key = mb_strtolower($label);
            if (!isset($normalized[$key])) {
                $normalized[$key] = $label;
            }
        }

        return array_values($normalized);
    }

    public static function extractFromPayload(array $payload): array
    {
        $types = self::normalizeList($payload['defect_types'] ?? []);

        if (empty($types) && array_key_exists('defect_types_json', $payload)) {
            $types = self::normalizeList($payload['defect_types_json']);
        }

        return $types;
    }

    public static function labels($source): array
    {
        if (is_object($source)) {
            if (isset($source->defect_types)) {
                return self::normalizeList($source->defect_types);
            }

            return [];
        }

        if (is_array($source)) {
            if (array_key_exists('defect_types', $source)) {
                return self::normalizeList($source['defect_types']);
            }

            if (array_key_exists('defect_types_json', $source)) {
                return self::normalizeList($source['defect_types_json']);
            }
        }

        return self::normalizeList($source);
    }

    public static function text($source, string $empty = '-'): string
    {
        $types = self::labels($source);

        return !empty($types) ? implode(', ', $types) : $empty;
    }
}
