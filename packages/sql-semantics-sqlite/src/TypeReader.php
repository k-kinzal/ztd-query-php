<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Numbers;
use SqlSemantics\Core\Ast\TokenGroups;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;

/**
 * Reads a declared type name into its conventional identity and its storage affinity.
 *
 * The database treats a declared type as a name: the affinity follows the
 * name text, and the numeric arguments are ignored. Names the documentation
 * lists become built-in types; other names are kept as named types. Integer
 * arguments are kept as the length, or precision and scale, the standard
 * spelling means; other arguments stay only in the typed declaration.
 *
 * @visibility SqlSemantics
 */
final class TypeReader
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
     * Reads a declared type; a column without one holds values of any storage class.
     */
    public function read(Node $node, ?Node $table): TypeDeclaration
    {
        $tokens = $node->tokens();
        $names = [];
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                break;
            }
            $names[] = (new NameRules())->name($token);
        }
        if ($names === []) {
            return new TypeDeclaration(new TypeDescriptor(Builtin::Dynamic, affinity: Affinity::Blob));
        }
        $spelling = \SqlSemantics\Statement\Identifier\Ascii::upper(implode(' ', $names));
        $name = self::NAMES[$spelling] ?? new TypeName($names);
        $affinity = $this->affinity($spelling);
        if ($name === Builtin::Any && $table !== null && $this->strict($table)) {
            $affinity = Affinity::Blob;
        }
        $arguments = array_map(Numbers::integer(...), Numbers::arguments(TokenGroups::parentheses($tokens)[0] ?? []));
        [$length, $precision, $scale] = $this->modifiers($name, $arguments);

        return new TypeDeclaration(new TypeDescriptor($name, $length, $precision, $scale, affinity: $affinity));
    }

    /**
     * Computes storage affinity from the declared name text in the documented precedence order.
     */
    public function affinity(string $spelling): Affinity
    {
        if (str_contains($spelling, 'INT')) {
            return Affinity::Integer;
        }
        if (str_contains($spelling, 'CHAR') || str_contains($spelling, 'CLOB') || str_contains($spelling, 'TEXT')) {
            return Affinity::Text;
        }
        if ($spelling === '' || str_contains($spelling, 'BLOB')) {
            return Affinity::Blob;
        }
        if (str_contains($spelling, 'REAL') || str_contains($spelling, 'FLOA') || str_contains($spelling, 'DOUB')) {
            return Affinity::Real;
        }

        return Affinity::Numeric;
    }

    /**
     * Reports whether a rowid alias can be declared: the type is spelled exactly INTEGER.
     */
    public static function rowidAlias(Node $declaration): bool
    {
        $type = Tree::outer($declaration, ['typetoken'])[0] ?? null;
        $tokens = $type === null ? [] : $type->tokens();

        return count($tokens) === 1 && strcasecmp((new NameRules())->name($tokens[0]), 'INTEGER') === 0;
    }

    /**
     * Keeps integer arguments in the slots the standard spelling gives them; other arguments have no meaning here.
     *
     * @param list<int|null> $arguments
     * @return array{?int, ?int, ?int} Length, precision and scale
     */
    public function modifiers(Builtin|TypeName $name, array $arguments): array
    {
        if ($name instanceof TypeName || in_array(null, $arguments, true) || $arguments === [] || count($arguments) > 2) {
            return [null, null, null];
        }
        $exact = in_array($name, [Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision], true);
        if ($exact) {
            return $arguments[0] < 0 ? [null, null, null] : [null, $arguments[0], $arguments[1] ?? null];
        }
        if (count($arguments) !== 1 || $arguments[0] < 0) {
            return [null, null, null];
        }

        return $name->isTemporal() ? [null, $arguments[0], null] : [$arguments[0], null, null];
    }

    /**
     * Reports whether the table declares the STRICT option, which stores ANY values as written.
     */
    public function strict(Node $table): bool
    {
        foreach (Tree::outer($table, ['table_option']) as $option) {
            if (\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($option)) === 'STRICT') {
                return true;
            }
        }

        return false;
    }
}
