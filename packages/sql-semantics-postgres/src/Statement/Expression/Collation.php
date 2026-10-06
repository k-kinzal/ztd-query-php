<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A value with an explicit collation: `x COLLATE name`.
 *
 * Mirrors PostgreSQL's `CollateClause` node.
 *
 * Rule: PG-COLLATE-001. Facts: the type and NULL fact of the operand; the
 * collation is a catalog object a schema can define, so its name is not
 * checked. A type that has no collation support makes the clause invalid.
 * The operand must keep its place without parentheses (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-COLLATE-EXPRS,
 * https://www.postgresql.org/docs/17/collation.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a collation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT 'a' COLLATE \"C\"");
 *     [$query->field(0)->expression->collation->last()->value, $query->toString()] // => ['C', "SELECT 'a' COLLATE \"C\""]
 */
final class Collation implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $operand The value
     * @param DottedName $collation The collation name
     */
    public function __construct(public readonly Scalar $operand, public readonly DottedName $collation)
    {
        Check::input((new Precedence())->before($operand, Precedence::COLLATE), 'The operand of COLLATE needs parentheses to keep its place.');
    }

    /**
     * Names an unaliased result column as the operand does.
     */
    public function outputName(): ?Name
    {
        return (new ColumnNaming())->name($this->operand);
    }

    /**
     * Derives the operand and checks that its type has collations.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);

        return new ScalarFact((new OperandChecks())->collatable($derivation, $fact->type), $fact->nullability);
    }

    /**
     * Writes the operand, COLLATE and the collation name.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('COLLATE');
        (new Spelling())->dotted($out, $this->collation->parts, NameUse::Qualifier);
    }
}
