<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The declared type of a SQLite column: its text as SQLite reports it and the affinity SQLite derives from it.
 *
 * Rule: SQLITE-COLUMN-AFFINITY-001. A declared type that is one of the
 * standard names ANY, BLOB, INT, INTEGER, REAL and TEXT, compared without
 * regard to ASCII case, is reported in upper case. The affinity follows the
 * five ordered substring rules of the manual, applied to the declared text
 * without regard to ASCII case; a column without a declared type has BLOB
 * affinity.
 * A column of an ordinary table can hold a value of any storage class. A
 * column of a STRICT table holds only the storage class its standard type
 * names (INT and INTEGER: INTEGER; REAL: REAL, with integers converted; TEXT;
 * BLOB) or NULL, and a STRICT column of type ANY holds any value unchanged,
 * which is BLOB affinity.
 * Source: https://sqlite.org/datatype3.html#determination_of_column_affinity,
 * https://sqlite.org/stricttables.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a declared type
 *     $domain = new \SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain('integer');
 *     [$domain->name(), $domain->affinity, $domain->standard] // => ['INTEGER', \SqlSemantics\Platform\Sqlite\Statement\Type\Affinity::Integer, true]
 * @example Deriving the affinity of a type that is not a standard name
 *     (new \SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain('VARCHAR(10)'))->affinity // => \SqlSemantics\Platform\Sqlite\Statement\Type\Affinity::Text
 */
final class ColumnDomain implements TypeDescriptor
{
    use Snapshot;

    /**
     * The standard type names, which are the only ones a STRICT table allows.
     */
    private const STANDARD = ['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'];

    /**
     * @var string The declared type as SQLite reports it; empty when the column declares no type
     */
    public readonly string $declared;

    /**
     * @var bool Whether the declared type is one of the standard names
     */
    public readonly bool $standard;

    /**
     * @var Affinity The affinity derived from the declared text
     */
    public readonly Affinity $affinity;

    /**
     * @param string $declared The declared type text; empty when the column declares no type
     * @param bool $strict Whether the column belongs to a STRICT table
     * @param bool $comparable Whether SQLite compares the text with the standard names; false only for a quoted text it cannot unquote before that comparison
     */
    public function __construct(string $declared, public readonly bool $strict = false, bool $comparable = true)
    {
        $text = strtr($declared, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $this->standard = $comparable && in_array($text, self::STANDARD, true);
        $this->declared = $this->standard ? $text : $declared;
        $this->affinity = match (true) {
            $this->standard && $strict && $text === 'ANY' => Affinity::Blob,
            str_contains($text, 'INT') => Affinity::Integer,
            str_contains($text, 'CHAR'), str_contains($text, 'CLOB'), str_contains($text, 'TEXT') => Affinity::Text,
            $text === '' && $comparable, str_contains($text, 'BLOB') => Affinity::Blob,
            str_contains($text, 'REAL'), str_contains($text, 'FLOA'), str_contains($text, 'DOUB') => Affinity::Real,
            default => Affinity::Numeric,
        };
    }

    /**
     * Tells whether the column declares a type at all: an empty declared text is a type only when SQLite derived NUMERIC affinity from a quoted empty word.
     */
    public function typed(): bool
    {
        return $this->declared !== '' || $this->affinity !== Affinity::Blob;
    }

    /**
     * Tells whether the declared type is the standard name INTEGER, the type of a column that can be the row identifier.
     */
    public function rowidCapable(): bool
    {
        return $this->standard && $this->declared === 'INTEGER';
    }

    /**
     * Names the type as SQLite reports it.
     */
    public function name(): string
    {
        return $this->declared;
    }
}
