<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

/**
 * Decodes structural choices directly from bytes so shrinking preserves neighboring operations.
 */
final class SequenceInput
{
    /**
     * @return list<SequenceCommand>
     */
    public function commands(string $input): array
    {
        $commands = [];
        $nextId = 4;
        foreach (str_split($input === '' ? "\0" : substr($input, 0, 256), 8) as $bytes) {
            $operation = ord($bytes[0]) % 8;
            $id = ord($bytes[1] ?? "\0") % $nextId + 1;
            $value = ord($bytes[2] ?? "\0") - 128;
            $name = ['plain', "O'Brien", "\u{2603}", '', null][ord($bytes[3] ?? "\0") % 5];
            $predicate = self::predicate($id, ord($bytes[4] ?? "\0"));
            $expression = self::expression(substr($bytes, 5));
            $command = match ($operation) {
                0 => new SequenceCommand("SELECT id, $expression AS value FROM fuzz_rows WHERE $predicate ORDER BY id", [], true),
                1 => new SequenceCommand('INSERT INTO fuzz_rows (id, quantity, name) VALUES (?, ?, ?)', [$nextId++, $value, $name], false),
                2 => new SequenceCommand("UPDATE fuzz_rows SET quantity = $expression WHERE $predicate", [], false),
                3 => new SequenceCommand("DELETE FROM fuzz_rows WHERE $predicate", [], false),
                4 => new SequenceCommand("WITH chosen AS (SELECT id, quantity FROM fuzz_rows WHERE $predicate) SELECT id, quantity FROM chosen ORDER BY id", [], true),
                5 => new SequenceCommand('SELECT a.id, b.quantity FROM fuzz_rows a LEFT JOIN fuzz_rows b ON a.id = b.id WHERE a.id >= ? ORDER BY a.id', [$id], true),
                6 => new SequenceCommand('INSERT INTO fuzz_rows (id, quantity, name) VALUES (?, ?, ?), (?, ?, ?)', [$nextId++, $value, $name, $id, $value, $name], false),
                7 => new SequenceCommand("SELECT id FROM fuzz_rows WHERE $predicate UNION SELECT id FROM fuzz_rows WHERE quantity < 0 ORDER BY id", [], true),
            };
            $commands[] = $command;
        }

        return $commands;
    }

    /**
     * Compose predicates with existing rows, absent rows and a derived subquery.
     *
     * @param int<0, 255> $choice
     */
    public static function predicate(int $id, int $choice): string
    {
        return match ($choice % 4) {
            0 => "id = $id",
            1 => "id <= $id OR quantity IS NULL",
            2 => "id IN (SELECT id FROM (SELECT id FROM fuzz_rows WHERE id >= $id LIMIT 1000) AS chosen_ids)",
            3 => "NOT (id < $id) AND (name IS NULL OR quantity >= 0)",
        };
    }

    /**
     * Compose nested arithmetic, CASE and null handling instead of choosing an entire query by seed.
     */
    public static function expression(string $bytes): string
    {
        $expression = 'quantity';
        foreach (str_split($bytes) as $byte) {
            $value = ord($byte) % 9 + 1;
            $expression = match (ord($byte) % 4) {
                0 => "($expression + $value)",
                1 => "($expression - $value)",
                2 => "COALESCE($expression, $value)",
                3 => "CASE WHEN id > $value THEN $expression ELSE $value END",
            };
        }

        return $expression;
    }
}
