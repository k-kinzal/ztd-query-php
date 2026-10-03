<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;

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
     * Reads a declared type name, its affinity, and the table options that change it.
     */
    public function read(Node $node, Language $language, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader())->read($node, $table);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }
}
