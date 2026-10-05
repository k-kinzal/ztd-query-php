<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reads a definition value the way the `defGet*` functions of PostgreSQL do.
 *
 * Rule: PG-DEFINE-VALUE-001. The grammar gives a definition value as a type
 * name (`func_type`), a reserved keyword or `NONE` (a string node holding the
 * lower-case word), an operator (a name list), a number (an integer node when
 * it fits in 32 bits, otherwise a float node) or a string. The names of a
 * type name are its written parts, or `pg_catalog` and the internal name for
 * a type keyword, as `SystemTypeName` builds them; its text is those names
 * joined by dots with `%TYPE` and `[]` appended as `TypeNameToString` does;
 * modifiers and SETOF are not part of either. The text of a float is its
 * canonical spelling, which the server would take as written; no command
 * compares a float's text with a word, so the difference shows only in
 * messages. Terminates: no recursion.
 * Source: `defGetString`, `defGetQualifiedName` and `defGetInt32` in `src/backend/commands/define.c`,
 * `appendTypeNameToBuffer` in `src/backend/parser/parse_type.c` and `def_arg` in `src/backend/parser/gram.y` of PostgreSQL 17.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ArgumentText
{
    /**
     * Answers the text the server reads from a value.
     */
    public function text(TypeName|KeywordWord|OperatorName|SignedNumber|StringConstant $value): string
    {
        return match (true) {
            $value instanceof TypeName => $this->joined($this->names($value)) . ($value->designation instanceof ColumnDesignation ? '%TYPE' : '') . ($value->array !== null ? '[]' : ''),
            $value instanceof KeywordWord => $value->word->value,
            $value instanceof OperatorName => $this->joined([...$value->qualifiers, $value->name]),
            $value instanceof SignedNumber => $this->number($value),
            $value instanceof StringConstant => $value->value,
        };
    }

    /**
     * Answers the names of a type name: its written parts, or `pg_catalog` and the internal name of a type keyword.
     *
     * @return non-empty-list<Name>
     */
    public function names(TypeName $type): array
    {
        $designation = $type->designation;
        if ($designation instanceof NamedDesignation || $designation instanceof ColumnDesignation) {
            return $designation->name->parts;
        }

        return [new Name('pg_catalog'), $designation->catalogName()];
    }

    /**
     * Answers the value of a number the grammar reads as an integer node, or null for a float node.
     */
    public function integer(SignedNumber $number): ?int
    {
        $magnitude = $number->magnitude;
        if (!$magnitude instanceof IntegerConstant || !(new Numerals())->within($magnitude->digits, '2147483647')) {
            return null;
        }

        return $number->negative ? -(int) $magnitude->digits : (int) $magnitude->digits;
    }

    /**
     * Answers the text of a number: the integer value, or the canonical spelling of a float.
     */
    public function number(SignedNumber $number): string
    {
        $integer = $this->integer($number);
        if ($integer !== null) {
            return (string) $integer;
        }
        $magnitude = $number->magnitude;
        $digits = $magnitude instanceof IntegerConstant ? $magnitude->digits : $magnitude->integer . '.' . $magnitude->fraction . ($magnitude->exponent === null ? '' : 'e' . $magnitude->exponent);

        return ($number->negative ? '-' : '') . $digits;
    }

    /**
     * Joins names with dots.
     *
     * @param list<Name> $names
     */
    public function joined(array $names): string
    {
        return implode('.', array_map(static fn (Name $name): string => $name->value, $names));
    }
}
