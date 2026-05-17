<?php

declare(strict_types=1);

namespace MyAds\Plugins\ArabicFixer;

final class ArabicStringRepair
{
    public static function isMojibake(?string $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        // Single-layer mojibake
        if (preg_match('/[\x{00D8}\x{00D9}][\x{0080}-\x{00BF}]/u', $value)) {
            return true;
        }

        // Multi-layer mojibake (containing double UTF-8 encoded D8/D9 bytes)
        if (str_contains($value, 'Ã˜') || str_contains($value, 'Ã™')) {
            return true;
        }

        return false;
    }

    public static function fix(?string $value): ?string
    {
        if (!is_string($value) || $value === '' || !self::isMojibake($value)) {
            return null;
        }

        $current = $value;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $decoded = @mb_convert_encoding($current, 'Windows-1252', 'UTF-8');

            if (!is_string($decoded) || $decoded === '' || $decoded === $current) {
                break;
            }

            if (!mb_check_encoding($decoded, 'UTF-8')) {
                break;
            }

            $current = $decoded;

            if (!self::isMojibake($current)) {
                break;
            }
        }

        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $current)) {
            return null;
        }

        return $current !== $value ? $current : null;
    }
}
