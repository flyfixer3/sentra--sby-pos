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

        if (empty($types) && !empty($payload['defect_type'])) {
            $types = self::normalizeList((string) $payload['defect_type']);
        }

        return $types;
    }

    public static function legacyLabel(array $types, ?string $fallback = null): ?string
    {
        $types = self::normalizeList($types);
        if (!empty($types)) {
            return implode(', ', $types);
        }

        $fallback = trim((string) $fallback);
        return $fallback !== '' ? $fallback : null;
    }
}
