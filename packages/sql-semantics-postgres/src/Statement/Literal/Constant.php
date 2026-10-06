<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A numeric, string or bit-string constant used as an expression.
 *
 * Rule: PG-CONSTANT-001. Facts: an integer constant is `integer` when it
 * fits 32 bits; a numeric constant written as an integer is `bigint` when it
 * fits 64 bits and `numeric` otherwise, as `make_const` reads the text of a
 * `T_Float` node first as `int8` and then as `numeric`; a constant with a
 * decimal point or exponent is `numeric`; a string constant
 * is of the pseudo-type `unknown` until its context resolves it; a bit-string
 * constant is `bit`. A constant is never NULL and gives a result column no
 * name. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS,
 * https://www.postgresql.org/docs/17/typeconv-overview.html, `make_const` in
 * `src/backend/parser/parse_node.c` of PostgreSQL 17. Status: Implemented.
 *
 * @visibility public
 * @example Reading the type of an integer constant too large for 32 bits
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 3000000000');
 *     $query->field(0)->type->descriptor->name() // => 'bigint'
 */
final class Constant implements Scalar, OutputNaming, IntegerValued
{
    use Snapshot;

    /**
     * @param IntegerConstant|NumericConstant|StringConstant|BitStringConstant $value The exact value
     */
    public function __construct(public readonly IntegerConstant|NumericConstant|StringConstant|BitStringConstant $value)
    {
    }

    /**
     * Answers the catalog type of the constant.
     */
    public function builtin(): Builtin
    {
        if ($this->value instanceof IntegerConstant) {
            return Builtin::Int4;
        }
        if ($this->value instanceof NumericConstant) {
            $integer = (new Numerals())->integer($this->value->text);

            return $integer !== null && (new Numerals())->within($integer, '9223372036854775807') ? Builtin::Int8 : Builtin::Numeric;
        }

        return $this->value instanceof StringConstant ? Builtin::Unknown : Builtin::Bit;
    }

    /**
     * Answers the integer an integer constant, or a string constant spelling one, denotes.
     */
    public function integerValue(): ?string
    {
        if ($this->value instanceof IntegerConstant) {
            return $this->value->digits;
        }
        if (!$this->value instanceof StringConstant || preg_match('/\A\s*([-+]?)([0-9]+)\s*\z/', $this->value->value, $match) !== 1) {
            return null;
        }
        $digits = (new Numerals())->canonical($match[2]);

        return ($match[1] === '-' && $digits !== '0' ? '-' : '') . $digits;
    }

    /**
     * Gives a result column no name.
     */
    public function outputName(): ?Name
    {
        return null;
    }

    /**
     * Derives the type of the constant; a constant is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known($this->builtin()), Nullability::NotNull);
    }

    /**
     * Writes the constant.
     */
    public function render(Output $out): void
    {
        $out->node($this->value);
    }
}
