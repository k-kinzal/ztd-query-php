<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The sql_mode of a session: the set of mode names it holds, in the order the server writes them.
 *
 * A combination mode (ANSI, TRADITIONAL) adds the modes it stands for. Names are read without
 * regard to case; a name the server does not know is refused by the caller. MySQL 5.6 and 5.7
 * also know NO_AUTO_CREATE_USER and the combination modes of other database systems, in the
 * places 8.0 left unused, and lack TIME_TRUNCATE_FRACTIONAL; their ANSI adds ONLY_FULL_GROUP_BY
 * from 5.7 on (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html,
 * https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html.
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
     * The mode names of MySQL 5.6 and 5.7 in the order the server writes them.
     */
    public const LEGACY_NAMES = [
        'REAL_AS_FLOAT', 'PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NOT_USED', 'ONLY_FULL_GROUP_BY',
        'NO_UNSIGNED_SUBTRACTION', 'NO_DIR_IN_CREATE', 'POSTGRESQL', 'ORACLE', 'MSSQL', 'DB2',
        'MAXDB', 'NO_KEY_OPTIONS', 'NO_TABLE_OPTIONS', 'NO_FIELD_OPTIONS', 'MYSQL323', 'MYSQL40', 'ANSI',
        'NO_AUTO_VALUE_ON_ZERO', 'NO_BACKSLASH_ESCAPES', 'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'NO_ZERO_IN_DATE',
        'NO_ZERO_DATE', 'ALLOW_INVALID_DATES', 'ERROR_FOR_DIVISION_BY_ZERO', 'TRADITIONAL', 'NO_AUTO_CREATE_USER',
        'HIGH_NOT_PRECEDENCE', 'NO_ENGINE_SUBSTITUTION', 'PAD_CHAR_TO_FULL_LENGTH',
    ];

    /**
     * The modes each combination mode of MySQL 5.6 and 5.7 adds; ANSI adds ONLY_FULL_GROUP_BY too from 5.7 on.
     */
    public const LEGACY_COMBINATIONS = [
        'ANSI' => ['REAL_AS_FLOAT', 'PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE'],
        'TRADITIONAL' => ['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_AUTO_CREATE_USER', 'NO_ENGINE_SUBSTITUTION'],
        'POSTGRESQL' => ['PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NO_KEY_OPTIONS', 'NO_TABLE_OPTIONS', 'NO_FIELD_OPTIONS'],
        'ORACLE' => ['PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NO_KEY_OPTIONS', 'NO_TABLE_OPTIONS', 'NO_FIELD_OPTIONS', 'NO_AUTO_CREATE_USER'],
        'MSSQL' => ['PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NO_KEY_OPTIONS', 'NO_TABLE_OPTIONS', 'NO_FIELD_OPTIONS'],
        'DB2' => ['PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NO_KEY_OPTIONS', 'NO_TABLE_OPTIONS', 'NO_FIELD_OPTIONS'],
        'MAXDB' => ['PIPES_AS_CONCAT', 'ANSI_QUOTES', 'IGNORE_SPACE', 'NO_KEY_OPTIONS', 'NO_TABLE_OPTIONS', 'NO_FIELD_OPTIONS', 'NO_AUTO_CREATE_USER'],
        'MYSQL323' => ['HIGH_NOT_PRECEDENCE'],
        'MYSQL40' => ['HIGH_NOT_PRECEDENCE'],
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
     * @param GrammarRelease $release The release whose modes these are
     */
    public function __construct(array $modes, public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
        $set = [];
        foreach ($modes as $mode) {
            $set[$mode] = true;
            foreach (self::combinations($release)[$mode] ?? [] as $implied) {
                $set[$implied] = true;
            }
        }
        $this->modes = $set;
    }

    /**
     * Answers the mode names of a release, in the order the server writes them.
     *
     * @return list<string>
     */
    public static function names(GrammarRelease $release): array
    {
        return $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744 ? self::LEGACY_NAMES : self::NAMES;
    }

    /**
     * Answers the modes each combination mode of a release adds.
     *
     * @return array<string, list<string>>
     */
    public static function combinations(GrammarRelease $release): array
    {
        if ($release === GrammarRelease::MySql5651) {
            return self::LEGACY_COMBINATIONS;
        }

        return $release === GrammarRelease::MySql5744 ? ['ANSI' => [...self::LEGACY_COMBINATIONS['ANSI'], 'ONLY_FULL_GROUP_BY']] + self::LEGACY_COMBINATIONS : self::COMBINATIONS;
    }

    /**
     * Reads a mode list, or answers null when it names an unknown mode.
     */
    public static function parse(string $text, GrammarRelease $release = GrammarRelease::MySql847): ?self
    {
        $modes = [];
        foreach (explode(',', strtoupper($text)) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (!in_array($name, self::names($release), true) || str_starts_with($name, 'NOT_USED')) {
                return null;
            }
            $modes[] = $name;
        }

        return new self($modes, $release);
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
        return implode(',', array_values(array_filter(self::names($this->release), fn (string $name): bool => isset($this->modes[$name]))));
    }
}
