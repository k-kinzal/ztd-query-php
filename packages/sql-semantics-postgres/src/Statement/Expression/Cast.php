<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * A conversion of a value to a type: `x::t` or `CAST(x AS t)`.
 *
 * Mirrors PostgreSQL's `TypeCast` node. Both spellings are the same node;
 * the spelling is kept because the token sequences differ.
 *
 * Rule: PG-CAST-001. Facts: the type the type name denotes; the NULL fact of
 * the operand, since a cast of NULL is NULL and a cast of a value is a value.
 * An unaliased result column is named after the operand when the operand
 * names one firmly, and otherwise after the type. The operand of `::` must
 * keep its place without parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-TYPE-CASTS,
 * https://www.postgresql.org/docs/17/sql-createcast.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Cast implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $operand The converted value
     * @param TypeName $type The target type
     * @param CastSpelling $spelling How the cast is written
     */
    public function __construct(public readonly Scalar $operand, public readonly TypeName $type, public readonly CastSpelling $spelling)
    {
        Check::input($spelling === CastSpelling::Function || (new Precedence())->before($operand, Precedence::TYPECAST), 'The operand of :: needs parentheses to keep its place.');
    }

    /**
     * Names an unaliased result column after the operand, or after the type.
     */
    public function outputName(): ?Name
    {
        return (new ColumnNaming())->name($this);
    }

    /**
     * Derives the operand and the target type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $this->type->deriveClause($derivation, $environment);
        $type = $this->type->typeFact($derivation->context);
        if ($type instanceof Invalid) {
            $derivation->report($type->cause);
        }

        return new ScalarFact($type, $operand->nullability);
    }

    /**
     * Writes the operand, `::` and the type, or the CAST form.
     */
    public function render(Output $out): void
    {
        if ($this->spelling === CastSpelling::Operator) {
            $out->node($this->operand)->symbol('::')->node($this->type);

            return;
        }
        $out->keyword('CAST')->symbol('(')->node($this->operand)->keyword('AS')->node($this->type)->symbol(')');
    }
}
