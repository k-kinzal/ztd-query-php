<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Construction\Derivation;
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
 * `MERGE_ACTION()`: the action a MERGE performed for the row returned (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `MergeSupportFunc` node. Rule: PG-MERGE-ACTION-001.
 * Facts: `text`, never NULL (`INSERT`, `UPDATE` or `DELETE`). The result
 * column is named `merge_action`.
 * Source: https://www.postgresql.org/docs/17/functions-merge-support.html. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\MergeAction())->outputName()->value // => 'merge_action'
 */
final class MergeAction implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('merge_action');
    }

    /**
     * Derives the documented type and NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Builtin::Text), Nullability::NotNull);
    }

    /**
     * Writes MERGE_ACTION().
     */
    public function render(Output $out): void
    {
        $out->keyword('MERGE_ACTION')->glue()->symbol('(')->symbol(')');
    }
}
