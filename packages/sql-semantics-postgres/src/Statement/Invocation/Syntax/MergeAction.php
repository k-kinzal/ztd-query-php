<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * `MERGE_ACTION()`: the action a MERGE performed for the row returned (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `MergeSupportFunc` node. Rule: PG-MERGE-ACTION-001.
 * Facts: `text`, never NULL (`INSERT`, `UPDATE` or `DELETE`). The result
 * column is named `merge_action`.
 * Source: https://www.postgresql.org/docs/17/functions-merge-support.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
