<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Contract\GrammarRelease;

/**
 * Looks up the native functions of a MySQL release: the functions a generic call `name(...)` reaches before a loadable or stored function.
 *
 * Rule: MYSQL-NATIVE-FUNCTIONS-001. The server resolves an unqualified call
 * of a name that is no keyword first against its native functions, then
 * against loadable functions and then against stored functions of the
 * default database; a qualified name `db.name(...)` is always a stored
 * function. The names are compared without regard to letter case. A native
 * function accepts a fixed range of argument counts; another count is the
 * error ER_WRONG_PARAMCOUNT_TO_NATIVE_FCT. The catalogs
 * (NativeCatalog, NativeSpatialCatalog, NativeInternalCatalog) list the
 * names of every release with their argument counts and result codes.
 * Terminates: one table lookup.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html,
 * https://github.com/mysql/mysql-server/blob/8.4/sql/item_create.cc.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NativeFunctions
{
    /**
     * The mask bit of each release.
     */
    private const BITS = [
        'mysql-5.6.51' => 1, 'mysql-5.7.44' => 2, 'mysql-8.0.44' => 4, 'mysql-8.1.0' => 8, 'mysql-8.2.0' => 16,
        'mysql-8.3.0' => 32, 'mysql-8.4.7' => 64, 'mysql-9.0.1' => 128, 'mysql-9.1.0' => 256,
    ];

    /**
     * The mask bit of a function reserved for the views of the data dictionary.
     */
    private const RESERVED = 512;

    /**
     * Tells whether a name is a native function of the release.
     */
    public function exists(GrammarRelease $release, string $name): bool
    {
        return $this->rows($release, $name) !== [];
    }

    /**
     * Answers the row that accepts the argument count, or null when the count is wrong.
     *
     * @return array{int, int, string, bool}|null The minimum and maximum argument count, the result code, and whether the function is reserved
     */
    public function row(GrammarRelease $release, string $name, int $arguments): ?array
    {
        foreach ($this->rows($release, $name) as $row) {
            if ($arguments >= $row[0] && ($row[1] === -1 || $arguments <= $row[1])) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Answers the rows of a name in a release.
     *
     * @return list<array{int, int, string, bool}>
     */
    public function rows(GrammarRelease $release, string $name): array
    {
        $key = strtoupper($name);
        $bit = self::BITS[$release->value] ?? 0;
        $rows = [];
        foreach (NativeCatalog::ROWS[$key] ?? NativeSpatialCatalog::ROWS[$key] ?? NativeInternalCatalog::ROWS[$key] ?? [] as [$mask, $minimum, $maximum, $code]) {
            if (($mask & $bit) !== 0) {
                $rows[] = [$minimum, $maximum, $code, ($mask & self::RESERVED) !== 0];
            }
        }

        return $rows;
    }
}
