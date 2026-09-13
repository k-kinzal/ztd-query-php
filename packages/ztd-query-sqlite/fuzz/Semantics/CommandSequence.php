<?php

declare(strict_types=1);

namespace Fuzz\Semantics;

/**
 * Compiles bytes into schema-valid operations over a bounded SQLite fixture.
 */
final class CommandSequence
{
    /**
     * Returns up to 32 commands with reachable empty, quoted, Unicode and boundary values.
     *
     * @return list<string>
     */
    public static function compile(string $input): array
    {
        $commands = [];
        $nextId = 3;
        foreach (str_split(substr($input, 0, 128), 4) as $bytes) {
            $operation = ord($bytes[0]) % 6;
            $id = ord($bytes[1] ?? "\0") % $nextId + 1;
            $value = ord($bytes[2] ?? "\0") - 128;
            $name = ['Alice', "O'Brien", '雪', ''][ord($bytes[3] ?? "\0") % 4];
            $literal = "'" . str_replace("'", "''", $name) . "'";
            $commands[] = match ($operation) {
                0 => "SELECT id, name, score FROM users WHERE score >= {$value} ORDER BY id",
                1 => 'INSERT INTO users (id, name, score) VALUES (' . $nextId++ . ", {$literal}, {$value})",
                2 => "UPDATE users SET score = score + {$value} WHERE id = {$id}",
                3 => "DELETE FROM users WHERE id = {$id}",
                4 => "SELECT COUNT(*) AS total, SUM(score) AS score FROM users WHERE id <= {$id}",
                5 => "UPDATE users SET name = {$literal} WHERE id = {$id}",
            };
        }

        return $commands;
    }
}
