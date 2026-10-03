<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * A SQLite declared type name and its derived storage preference.
 * Size annotations are part of the name; SQLite does not enforce them as size constraints.
 * @visibility public
 * @example Describing a conventional type and its storage affinity
 *     $type = new \SqlSemantics\Statement\Type\SqliteDeclaration('VARCHAR');
 *     $type->descriptor->affinity->value // => 'text'
 */
final class SqliteDeclaration
{
    private const NAMES = [
        'INT' => Builtin::Integer,
        'INTEGER' => Builtin::Integer,
        'TINYINT' => Builtin::TinyInt,
        'SMALLINT' => Builtin::SmallInt,
        'INT2' => Builtin::SmallInt,
        'MEDIUMINT' => Builtin::MediumInt,
        'BIGINT' => Builtin::BigInt,
        'INT8' => Builtin::BigInt,
        'CHARACTER' => Builtin::Char,
        'CHAR' => Builtin::Char,
        'NCHAR' => Builtin::Char,
        'NATIVE CHARACTER' => Builtin::Char,
        'VARCHAR' => Builtin::VarChar,
        'VARYING CHARACTER' => Builtin::VarChar,
        'NVARCHAR' => Builtin::VarChar,
        'TEXT' => Builtin::Text,
        'CLOB' => Builtin::Text,
        'BLOB' => Builtin::Blob,
        'REAL' => Builtin::Real,
        'FLOAT' => Builtin::Real,
        'DOUBLE' => Builtin::DoublePrecision,
        'DOUBLE PRECISION' => Builtin::DoublePrecision,
        'NUMERIC' => Builtin::Numeric,
        'DECIMAL' => Builtin::Numeric,
        'BOOLEAN' => Builtin::Boolean,
        'DATE' => Builtin::Date,
        'DATETIME' => Builtin::DateTime,
        'ANY' => Builtin::Any,
    ];

    /**
     * Type identity and storage preference derived from this declaration.
     */
    public readonly TypeDescriptor $descriptor;

    /**
     * The database's declared name, or null when the type is omitted.
     */
    public readonly ?string $name;

    /**
     * Whether SQLite recognizes its native type identity before retaining a custom name.
     * INTEGER rowid aliases and STRICT tables depend on this distinction.
     */
    public readonly bool $native;

    /**
     * A custom name can equal a native name after dequoting without acquiring its native identity.
     */
    public function __construct(?string $name = null, public readonly bool $strict = false, bool $custom = false)
    {
        assert($name === null || !str_contains($name, "\0"), 'A declared type name cannot contain NUL.');
        $spelling = strtoupper($name ?? '');
        $this->native = !$custom && in_array($spelling, ['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'], true);
        $this->name = $this->native ? $spelling : $name;
        $identity = $name === null ? Builtin::Dynamic : (self::NAMES[$spelling] ?? new TypeName([$name]));
        $this->descriptor = new TypeDescriptor($identity, affinity: $strict && $this->native && $identity === Builtin::Any ? Affinity::Blob : $this->affinity($spelling));
    }

    /**
     * Applies SQLite's documented substring precedence to the declared type name.
     */
    public function affinity(string $spelling): Affinity
    {
        $spelling = strtoupper($spelling);
        return match (true) {
            str_contains($spelling, 'INT') => Affinity::Integer,
            str_contains($spelling, 'CHAR'), str_contains($spelling, 'CLOB'), str_contains($spelling, 'TEXT') => Affinity::Text,
            $spelling === '', str_contains($spelling, 'BLOB') => Affinity::Blob,
            str_contains($spelling, 'REAL'), str_contains($spelling, 'FLOA'), str_contains($spelling, 'DOUB') => Affinity::Real,
            default => Affinity::Numeric,
        };
    }

    /**
     * Identifies the exact declaration that can provide an INTEGER PRIMARY KEY rowid alias.
     */
    public function permitsRowidAlias(): bool
    {
        return $this->native && $this->name === 'INTEGER';
    }

    /**
     * Preserves both the stored name and native identity. A zero size annotation keeps a custom namesake custom.
     */
    public function toString(): string
    {
        if ($this->name === null) {
            return '';
        }
        if ($this->native) {
            return $this->name;
        }
        $name = (new Name($this->name, \SqlSemantics\Statement\Identifier\Quote::Double))->toString();
        return $name . (in_array(strtoupper($this->name), ['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'], true) ? '(0)' : '');
    }
}
