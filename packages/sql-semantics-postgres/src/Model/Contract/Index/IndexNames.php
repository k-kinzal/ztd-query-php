<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

/**
 * Computes the native names shared by B-tree declarations and backing indexes.
 *
 * @visibility SqlSemantics
 */
final class IndexNames
{
    /**
     * Allocates distinct physical attribute labels in index occurrence order.
     * @param non-empty-list<string> $fields Exact key and covering columns
     * @return non-empty-list<string>
     */
    public static function attributes(array $fields): array
    {
        $seen = [];
        $labels = [];
        foreach ($fields as $original) {
            $candidate = $original;
            $pass = 1;
            while (isset($seen[$candidate])) {
                $suffix = (string) $pass;
                $candidate = self::clip($original, 63 - strlen($suffix)) . $suffix;
                ++$pass;
            }
            $seen[$candidate] = true;
            $labels[] = $candidate;
        }

        return $labels;
    }

    /**
     * Clips both name contributions to leave the full native suffix intact.
     */
    public static function candidate(string $table, string $attributes, string $suffix): string
    {
        $first = strlen($table);
        $second = strlen($attributes);
        $limit = 61 - strlen($suffix);
        while ($first + $second > $limit) {
            if ($first > $second) {
                --$first;
            } else {
                --$second;
            }
        }
        return self::clip($table, $first) . '_' . self::clip($attributes, $second) . '_' . $suffix;
    }

    /**
     * Keeps the largest UTF-8 prefix within an already checked byte budget.
     */
    public static function clip(string $value, int $limit): string
    {
        $length = min(max($limit, 0), strlen($value));
        while ($length < strlen($value) && (ord($value[$length]) & 0xc0) === 0x80) {
            --$length;
        }

        return substr($value, 0, $length);
    }
}
