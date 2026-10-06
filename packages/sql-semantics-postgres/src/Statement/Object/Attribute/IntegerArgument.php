<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ArgumentText;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An attribute value read as a 32-bit integer, such as `sspace` of an aggregate.
 *
 * `defGetInt32` accepts only an integer node, which the grammar builds for an
 * integer that fits in 32 bits; a larger integer or a number with a point or
 * an exponent is a float node.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `defGetInt32` in `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the state size of an aggregate
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE AGGREGATE total (int4) (sfunc = int4pl, stype = int4, sspace = 16)');
 *     $operation->statement->definition[2]->value->value() // => 16
 * @example Rejecting a number that is not a 32-bit integer
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\IntegerArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('2147483648'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class IntegerArgument implements AttributeArgument
{
    use Snapshot;

    /**
     * @param SignedNumber $number The integer as written
     */
    public function __construct(public readonly SignedNumber $number)
    {
        Check::input((new ArgumentText())->integer($number) !== null, 'An integer attribute is an integer that fits in 32 bits.');
    }

    /**
     * Answers the integer.
     */
    public function value(): int
    {
        return (new ArgumentText())->integer($this->number) ?? 0;
    }

    /**
     * Tells whether the reading reads an integer.
     */
    public function fits(Reading $reading): bool
    {
        return $reading === Reading::Integer;
    }

    /**
     * Derives nothing: a number holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the integer.
     */
    public function render(Output $out): void
    {
        $out->node($this->number);
    }
}
