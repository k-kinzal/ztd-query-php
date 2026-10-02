<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The declared type of a SQLite column: its text as declared and the affinity SQLite derives from it.
 *
 * Rule: SQLITE-COLUMN-AFFINITY-001. The affinity follows the five ordered
 * substring rules of the manual, applied to the declared text without regard
 * to ASCII case; an empty declared type has BLOB affinity. A column of an
 * ordinary table can still hold a value of any storage class.
 * Source: https://sqlite.org/datatype3.html#determination_of_column_affinity.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a declared type
 *     $domain = new \SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain('INTEGER');
 *     [$domain->name(), $domain->affinity] // => ['INTEGER', \SqlSemantics\Platform\Sqlite\Statement\Type\Affinity::Integer]
 */
final class ColumnDomain implements TypeDescriptor
{
    use Snapshot;

    /**
     * @var Affinity The affinity derived from the declared text
     */
    public readonly Affinity $affinity;

    /**
     * @param string $declared The declared type text; empty when the column declares no type
     */
    public function __construct(public readonly string $declared)
    {
        $text = strtr($declared, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $this->affinity = match (true) {
            str_contains($text, 'INT') => Affinity::Integer,
            str_contains($text, 'CHAR'), str_contains($text, 'CLOB'), str_contains($text, 'TEXT') => Affinity::Text,
            $text === '', str_contains($text, 'BLOB') => Affinity::Blob,
            str_contains($text, 'REAL'), str_contains($text, 'FLOA'), str_contains($text, 'DOUB') => Affinity::Real,
            default => Affinity::Numeric,
        };
    }

    /**
     * Names the type as declared.
     */
    public function name(): string
    {
        return $this->declared;
    }
}
