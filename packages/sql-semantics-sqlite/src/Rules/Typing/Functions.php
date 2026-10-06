<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Typing;

use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\WrongArgumentCount;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The result facts of the built-in SQL functions of SQLite.
 *
 * Rule: SQLITE-FUNCTION-RESULT-001. The table lists the core, aggregate,
 * window, date and time, and JSON functions of the manual with the number of
 * arguments each accepts, the storage classes of its result and its NULL
 * rule. Result codes: I, R, T, B name storage classes and combine (IR is
 * INTEGER or REAL); `*` is any class; `1` is the class of the first
 * argument; `+` is the class of any argument; `2` is the class of any
 * argument after the first; `L` is the class of the first or third argument.
 * NULL codes: N never NULL; P NULL exactly when an argument can be NULL; C
 * NULL only when every argument can be; F NULL when the first argument can
 * be; Y possibly NULL. Kind codes: s scalar, a aggregate (also usable as a
 * window function), w window only. A function that takes one argument as an
 * aggregate and more as a scalar has both rows. A name outside the table is
 * an application-defined or extension routine: its result depends on the
 * undeclared routine. Source: https://sqlite.org/lang_corefunc.html,
 * https://sqlite.org/lang_aggfunc.html, https://sqlite.org/windowfunctions.html#biwinfunc,
 * https://sqlite.org/lang_datefunc.html, https://sqlite.org/json1.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Functions
{
    /**
     * The functions by lower-case name: rows of minimum arguments, maximum arguments (-1 for any), result code, NULL code, kind.
     */
    private const TABLE = [
        'abs' => [[1, 1, 'IR', 'P', 's']],
        'changes' => [[0, 0, 'I', 'N', 's']],
        'char' => [[0, -1, 'T', 'N', 's']],
        'coalesce' => [[2, -1, '+', 'C', 's']],
        'concat' => [[1, -1, 'T', 'N', 's']],
        'concat_ws' => [[2, -1, 'T', 'F', 's']],
        'format' => [[1, -1, 'T', 'F', 's']],
        'glob' => [[2, 2, 'I', 'P', 's']],
        'hex' => [[1, 1, 'T', 'N', 's']],
        'ifnull' => [[2, 2, '+', 'C', 's']],
        'iif' => [[3, 3, '2', 'Y', 's']],
        'instr' => [[2, 2, 'I', 'P', 's']],
        'last_insert_rowid' => [[0, 0, 'I', 'N', 's']],
        'length' => [[1, 1, 'I', 'P', 's']],
        'like' => [[2, 3, 'I', 'P', 's']],
        'likelihood' => [[2, 2, '1', 'F', 's']],
        'likely' => [[1, 1, '1', 'F', 's']],
        'load_extension' => [[1, 2, '*', 'Y', 's']],
        'lower' => [[1, 1, 'T', 'P', 's']],
        'ltrim' => [[1, 2, 'T', 'P', 's']],
        'max' => [[1, 1, '1', 'Y', 'a'], [2, -1, '+', 'P', 's']],
        'min' => [[1, 1, '1', 'Y', 'a'], [2, -1, '+', 'P', 's']],
        'nullif' => [[2, 2, '1', 'Y', 's']],
        'octet_length' => [[1, 1, 'I', 'P', 's']],
        'printf' => [[1, -1, 'T', 'F', 's']],
        'quote' => [[1, 1, 'T', 'N', 's']],
        'random' => [[0, 0, 'I', 'N', 's']],
        'randomblob' => [[1, 1, 'B', 'N', 's']],
        'replace' => [[3, 3, 'T', 'P', 's']],
        'round' => [[1, 2, 'IR', 'P', 's']],
        'rtrim' => [[1, 2, 'T', 'P', 's']],
        'sign' => [[1, 1, 'I', 'Y', 's']],
        'sqlite_compileoption_get' => [[1, 1, 'T', 'Y', 's']],
        'sqlite_compileoption_used' => [[1, 1, 'I', 'P', 's']],
        'sqlite_offset' => [[1, 1, 'I', 'Y', 's']],
        'sqlite_source_id' => [[0, 0, 'T', 'N', 's']],
        'sqlite_version' => [[0, 0, 'T', 'N', 's']],
        'substr' => [[2, 3, 'TB', 'P', 's']],
        'substring' => [[2, 3, 'TB', 'P', 's']],
        'total_changes' => [[0, 0, 'I', 'N', 's']],
        'trim' => [[1, 2, 'T', 'P', 's']],
        'typeof' => [[1, 1, 'T', 'N', 's']],
        'unhex' => [[1, 2, 'B', 'Y', 's']],
        'unicode' => [[1, 1, 'I', 'Y', 's']],
        'unlikely' => [[1, 1, '1', 'F', 's']],
        'upper' => [[1, 1, 'T', 'P', 's']],
        'zeroblob' => [[1, 1, 'B', 'N', 's']],
        'avg' => [[1, 1, 'R', 'Y', 'a']],
        'count' => [[0, 1, 'I', 'N', 'a']],
        'group_concat' => [[1, 2, 'T', 'Y', 'a']],
        'string_agg' => [[2, 2, 'T', 'Y', 'a']],
        'sum' => [[1, 1, 'IR', 'Y', 'a']],
        'total' => [[1, 1, 'R', 'N', 'a']],
        'row_number' => [[0, 0, 'I', 'N', 'w']],
        'rank' => [[0, 0, 'I', 'N', 'w']],
        'dense_rank' => [[0, 0, 'I', 'N', 'w']],
        'percent_rank' => [[0, 0, 'R', 'N', 'w']],
        'cume_dist' => [[0, 0, 'R', 'N', 'w']],
        'ntile' => [[1, 1, 'I', 'N', 'w']],
        'lag' => [[1, 3, 'L', 'Y', 'w']],
        'lead' => [[1, 3, 'L', 'Y', 'w']],
        'first_value' => [[1, 1, '1', 'Y', 'w']],
        'last_value' => [[1, 1, '1', 'Y', 'w']],
        'nth_value' => [[2, 2, '1', 'Y', 'w']],
        'date' => [[0, -1, 'T', 'Y', 's']],
        'time' => [[0, -1, 'T', 'Y', 's']],
        'datetime' => [[0, -1, 'T', 'Y', 's']],
        'julianday' => [[0, -1, 'R', 'Y', 's']],
        'unixepoch' => [[0, -1, 'IR', 'Y', 's']],
        'strftime' => [[1, -1, 'T', 'Y', 's']],
        'timediff' => [[2, 2, 'T', 'Y', 's']],
        'json' => [[1, 1, 'T', 'Y', 's']],
        'jsonb' => [[1, 1, 'B', 'Y', 's']],
        'json_array' => [[0, -1, 'T', 'N', 's']],
        'jsonb_array' => [[0, -1, 'B', 'N', 's']],
        'json_array_length' => [[1, 2, 'I', 'Y', 's']],
        'json_error_position' => [[1, 1, 'I', 'Y', 's']],
        'json_extract' => [[2, -1, '*', 'Y', 's']],
        'jsonb_extract' => [[2, -1, '*', 'Y', 's']],
        'json_insert' => [[1, -1, 'T', 'Y', 's']],
        'jsonb_insert' => [[1, -1, 'B', 'Y', 's']],
        'json_object' => [[0, -1, 'T', 'N', 's']],
        'jsonb_object' => [[0, -1, 'B', 'N', 's']],
        'json_patch' => [[2, 2, 'T', 'Y', 's']],
        'jsonb_patch' => [[2, 2, 'B', 'Y', 's']],
        'json_pretty' => [[1, 2, 'T', 'Y', 's']],
        'json_remove' => [[1, -1, 'T', 'Y', 's']],
        'jsonb_remove' => [[1, -1, 'B', 'Y', 's']],
        'json_replace' => [[1, -1, 'T', 'Y', 's']],
        'jsonb_replace' => [[1, -1, 'B', 'Y', 's']],
        'json_set' => [[1, -1, 'T', 'Y', 's']],
        'jsonb_set' => [[1, -1, 'B', 'Y', 's']],
        'json_type' => [[1, 2, 'T', 'Y', 's']],
        'json_valid' => [[1, 2, 'I', 'Y', 's']],
        'json_quote' => [[1, 1, 'T', 'Y', 's']],
        'json_group_array' => [[1, 1, 'T', 'N', 'a']],
        'jsonb_group_array' => [[1, 1, 'B', 'N', 'a']],
        'json_group_object' => [[2, 2, 'T', 'N', 'a']],
        'jsonb_group_object' => [[2, 2, 'B', 'N', 'a']],
    ];

    /**
     * The storage classes a result code letter names.
     */
    private const CLASSES = ['I' => Storage::Integer, 'R' => Storage::Real, 'T' => Storage::Text, 'B' => Storage::Blob];

    /**
     * Answers the table row of a call, or null when the name is no built-in function or accepts no such argument count.
     *
     * @return array{int, int, string, string, string}|null
     */
    public function row(string $name, int $arguments): ?array
    {
        foreach (self::TABLE[Comparison::AsciiInsensitive->fold($name)] ?? [] as $row) {
            if ($arguments >= $row[0] && ($row[1] === -1 || $arguments <= $row[1])) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Tells whether a call computes one value from many rows: a built-in aggregate called without a window.
     */
    public function aggregates(string $name, int $arguments): bool
    {
        return ($this->row($name, $arguments)[4] ?? 's') === 'a';
    }

    /**
     * Answers the facts of the result of a call from the facts of its arguments.
     *
     * @param list<ScalarFact> $arguments
     */
    public function result(Name $name, array $arguments): ScalarFact
    {
        $row = $this->row($name->value, count($arguments));
        if ($row === null) {
            if (isset(self::TABLE[Comparison::AsciiInsensitive->fold($name->value)])) {
                return new ScalarFact(new Invalid(new WrongArgumentCount($name, count($arguments))), Nullability::Dependent);
            }

            return new ScalarFact(new Dependent([new UndeclaredRoutine(new QualifiedName($name))]), Nullability::Dependent);
        }
        $types = [];
        $nullable = [];
        foreach ($arguments as $position => $argument) {
            if (match ($row[2]) {
                '1' => $position === 0, '2' => $position > 0, 'L' => $position !== 1, default => true
            }) {
                $types[] = $argument->type;
            }
            $nullable[] = $argument->nullability;
        }
        $storages = [];
        foreach (str_split($row[2] === '*' ? 'IRTB' : $row[2]) as $letter) {
            if (isset(self::CLASSES[$letter])) {
                $storages[] = self::CLASSES[$letter];
            }
        }

        return new ScalarFact($storages === [] ? (new Storages())->either($types) : (new Storages())->fact($storages), $this->nullability($row[3], $nullable));
    }

    /**
     * Answers the NULL fact a NULL code gives for the NULL facts of the arguments.
     *
     * @param list<Nullability> $arguments
     */
    public function nullability(string $code, array $arguments): Nullability
    {
        if ($code === 'N' || $code === 'Y' || $arguments === []) {
            return $code === 'N' ? Nullability::NotNull : Nullability::Nullable;
        }
        if ($code === 'F') {
            return $arguments[0];
        }
        $result = $arguments[0];
        foreach ($arguments as $argument) {
            if ($code === 'P') {
                $result = $result->propagate($argument);
            } elseif ($argument === Nullability::NotNull || $result === Nullability::NotNull) {
                $result = Nullability::NotNull;
            } else {
                $result = $result->propagate($argument);
            }
        }

        return $result;
    }
}
