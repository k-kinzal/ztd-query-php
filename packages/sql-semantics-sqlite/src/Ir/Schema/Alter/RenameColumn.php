<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved column rename in an ordinary SQLite table.
 *
 * @visibility public
 * @example Renaming a declared column
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE t (value INTEGER)']);
 *     $semantics->analyze('ALTER TABLE t RENAME vAlUe TO amount', $declarations)->toString() // => 'ALTER TABLE "main"."t" RENAME COLUMN "value" TO amount'
 */
final class RenameColumn implements SemanticsIR
{
    use Snapshot;

    /**
     * SQLite 3.47.2 keywords unavailable in the bare destination position.
     */
    private const RESERVED_DESTINATIONS = [
        'ADD', 'ALL', 'ALTER', 'AND', 'AS', 'AUTOINCREMENT', 'BETWEEN', 'CASE', 'CHECK', 'COLLATE', 'COMMIT',
        'CONSTRAINT', 'CREATE', 'DEFAULT', 'DEFERRABLE', 'DELETE', 'DISTINCT', 'DROP', 'ELSE', 'ESCAPE', 'EXCEPT',
        'EXISTS', 'FOREIGN', 'FROM', 'GROUP', 'HAVING', 'IN', 'INDEX', 'INSERT', 'INTERSECT', 'INTO', 'IS',
        'ISNULL', 'JOIN', 'LIMIT', 'NOT', 'NOTHING', 'NOTNULL', 'NULL', 'ON', 'OR', 'ORDER', 'PRIMARY',
        'REFERENCES', 'RETURNING', 'SELECT', 'SET', 'TABLE', 'THEN', 'TO', 'TRANSACTION', 'UNION', 'UNIQUE',
        'UPDATE', 'USING', 'VALUES', 'WHEN', 'WHERE',
    ];

    /**
     * Fixes the table identity, the declared member and the destination rewrite mode.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        public readonly Identifier $column,
        public readonly Identifier $destination,
        public readonly bool $quoteReferences = true,
    ) {
        Check::input($schema->value !== '', 'An ALTER TABLE target needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A column rename cannot target the reserved SQLite namespace.');
        Check::input(
            $quoteReferences || (
                preg_match('/\A[A-Za-z_\x80-\xff][A-Za-z0-9_$\x80-\xff]*\z/', $destination->value) === 1
                && !str_starts_with($destination->value, "\xEF\xBB\xBF")
                && !in_array(strtr($destination->value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), self::RESERVED_DESTINATIONS, true)
            ),
            'A bare rename destination requires one admitted SQLite identifier token.',
        );
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the absolute table target and the named column operation.
     */
    public function toString(): string
    {
        $destination = $this->quoteReferences ? $this->destination->quoted() : $this->destination->value;

        return 'ALTER TABLE ' . $this->schema->quoted() . '.' . $this->table->quoted()
            . ' RENAME COLUMN ' . $this->column->quoted() . ' TO ' . $destination;
    }
}
