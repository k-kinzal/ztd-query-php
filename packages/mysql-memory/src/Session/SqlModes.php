<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

/**
 * The sql_mode of a session: the set of mode names it holds, in the order the server writes them.
 *
 * A combination mode (ANSI, TRADITIONAL) adds the modes it stands for. Names are read without
 * regard to case; a name the server does not know is refused by the caller.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html.
 *
 * @visibility public
 * @example Reading a mode list
 *     \MySqlMemory\Session\SqlModes::parse('strict_trans_tables,ansi_quotes')?->toString() // => 'ANSI_QUOTES,STRICT_TRANS_TABLES'
 */
final class SqlModes
{
    /**
     * The mode names in the order the server writes them.
     */
    public const NAMES = [
        'REAL_AS_FLOAT', 'PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NOT_USED', 'ONLY_FULL_GROUP_BY',
        'NO_UNSIGNED_SUBTRACTION', 'NO_DIR_IN_CREATE', 'NOT_USED_9', 'NOT_USED_10', 'NOT_USED_11', 'NOT_USED_12',
        'NOT_USED_13', 'NOT_USED_14', 'NOT_USED_15', 'NOT_USED_16', 'NOT_USED_17', 'NOT_USED_18', 'ANSI',
        'NO_AUTO_VALUE_ON_ZERO', 'NO_BACKSLASH_ESCAPES', 'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'NO_ZERO_IN_DATE',
        'NO_ZERO_DATE', 'ALLOW_INVALID_DATES', 'ERROR_FOR_DIVISION_BY_ZERO', 'TRADITIONAL', 'NOT_USED_29',
        'HIGH_NOT_PRECEDENCE', 'NO_ENGINE_SUBSTITUTION', 'PAD_CHAR_TO_FULL_LENGTH', 'TIME_TRUNCATE_FRACTIONAL',
    ];

    /**
     * The modes each combination mode adds.
     */
    public const COMBINATIONS = [
        'ANSI' => ['REAL_AS_FLOAT', 'PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'ONLY_FULL_GROUP_BY'],
        'TRADITIONAL' => ['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION'],
    ];

    /**
     * The default of MySQL 8.0 and later.
     */
    public const DEFAULT = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

    /**
     * @var array<string, true>
     */
    public readonly array $modes;

    /**
     * @param list<string> $modes The mode names, in upper case
     */
    public function __construct(array $modes)
    {
        $set = [];
        foreach ($modes as $mode) {
            $set[$mode] = true;
            foreach (self::COMBINATIONS[$mode] ?? [] as $implied) {
                $set[$implied] = true;
            }
        }
        $this->modes = $set;
    }

    /**
     * Reads a mode list, or answers null when it names an unknown mode.
     */
    public static function parse(string $text): ?self
    {
        $modes = [];
        foreach (explode(',', strtoupper($text)) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (!in_array($name, self::NAMES, true) || str_starts_with($name, 'NOT_USED')) {
                return null;
            }
            $modes[] = $name;
        }

        return new self($modes);
    }

    /**
     * Tells whether the set holds a mode.
     */
    public function has(string $mode): bool
    {
        return isset($this->modes[$mode]);
    }

    /**
     * Tells whether STRICT_TRANS_TABLES or STRICT_ALL_TABLES is set.
     */
    public function strict(): bool
    {
        return $this->has('STRICT_TRANS_TABLES') || $this->has('STRICT_ALL_TABLES');
    }

    /**
     * Writes the set as the server reports it.
     */
    public function toString(): string
    {
        return implode(',', array_values(array_filter(self::NAMES, fn (string $name): bool => isset($this->modes[$name]))));
    }
}
