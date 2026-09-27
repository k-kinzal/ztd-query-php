<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\TypeDeclaration;

/**
 * Sqlite TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    private const SUPPORTED = [
        Builtin::Unknown,
        Builtin::Dynamic,
        Builtin::Any,
        Builtin::TinyInt,
        Builtin::SmallInt,
        Builtin::MediumInt,
        Builtin::Integer,
        Builtin::BigInt,
        Builtin::Numeric,
        Builtin::Real,
        Builtin::DoublePrecision,
        Builtin::Boolean,
        Builtin::Char,
        Builtin::VarChar,
        Builtin::Text,
        Builtin::Blob,
        Builtin::Date,
        Builtin::DateTime,
    ];

    /**
     * Retains the language identity used in semantic output.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type name, its affinity, and the table options that change it.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader($this->dialect))->read($node, $table);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }
}
