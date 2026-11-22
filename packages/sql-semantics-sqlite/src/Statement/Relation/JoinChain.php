<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\FromScope;

/**
 * The terms of a FROM clause joined from left to right.
 *
 * A chain is a first term and the further terms in written order, each with
 * the operator before it and the constraint after it; SQLite joins them left
 * to right. The grammar also admits a constraint after the first term, which
 * SQLite rejects.
 *
 * Rule: SQLITE-JOIN-CHAIN-001. The terms are derived from left to right and
 * combined by SQLITE-JOIN-001; every ON condition sees all relations of the
 * chain. The shape of the chain is the row that `*` selects: every column of
 * every term except the merged columns of USING and NATURAL joins. An
 * unknown join type and a constraint without a join are reported.
 * Terminates: the terms are a finite list walked in a loop.
 * Source: https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class JoinChain implements Relation
{
    use Snapshot;

    /**
     * @var list<JoinStep> The further terms in written order
     */
    public readonly array $steps;

    /**
     * @param Relation $first The first term
     * @param list<JoinStep> $steps The further terms in written order
     * @param JoinOn|JoinUsing|null $constraint The constraint written after the first term
     */
    public function __construct(public readonly Relation $first, array $steps, public readonly JoinOn|JoinUsing|null $constraint = null)
    {
        $this->steps = Check::listOf($steps, JoinStep::class, 'The further terms of a join chain are join steps.');
        Check::input(!$first instanceof self, 'A join chain used as a term is written in parentheses.');
        Check::input($steps !== [] || $constraint !== null, 'A single term without a constraint is no join chain.');
    }

    /**
     * Derives the terms, the constraints and the shape of the joined rows.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [], true)->fact;
    }

    /**
     * Writes the terms in order.
     */
    public function render(Output $out): void
    {
        $out->node($this->first)->node($this->constraint);
        foreach ($this->steps as $step) {
            $out->node($step);
        }
    }
}
