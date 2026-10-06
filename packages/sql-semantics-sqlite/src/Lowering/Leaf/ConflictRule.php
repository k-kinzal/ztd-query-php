<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;

/**
 * Lowers conflict resolution clauses.
 *
 * Rule: SQLITE-CONFLICT-LOWER-001. Scope: resolvetype, raisetype, onconf,
 * orconf. Each names one of the five conflict resolution algorithms; an empty
 * clause names none. Source: https://sqlite.org/lang_conflict.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConflictRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `resolvetype`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function resolution(Node $resolvetype): ConflictResolution
    {
        $form = $this->lowering->productions->form($resolvetype);

        return match ($form->signature) {
            'resolvetype: raisetype' => $this->raised($form->node(0)),
            'resolvetype: IGNORE' => ConflictResolution::Ignore,
            'resolvetype: REPLACE' => ConflictResolution::Replace,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `raisetype`: the algorithms that RAISE can also name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function raised(Node $raisetype): ConflictResolution
    {
        $form = $this->lowering->productions->form($raisetype);

        return match ($form->signature) {
            'raisetype: ROLLBACK' => ConflictResolution::Rollback,
            'raisetype: ABORT' => ConflictResolution::Abort,
            'raisetype: FAIL' => ConflictResolution::Fail,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `onconf`: the algorithm of ON CONFLICT, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function onConflict(Node $onconf): ?ConflictResolution
    {
        $form = $this->lowering->productions->form($onconf);

        return match ($form->signature) {
            'onconf:' => null,
            'onconf: ON CONFLICT resolvetype' => $this->resolution($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `orconf`: the algorithm written after OR, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function orConflict(Node $orconf): ?ConflictResolution
    {
        $form = $this->lowering->productions->form($orconf);

        return match ($form->signature) {
            'orconf:' => null,
            'orconf: OR resolvetype' => $this->resolution($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
