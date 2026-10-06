<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;

/**
 * Reports the window frames the ordering of their own window cannot support.
 *
 * Rule: PG-WINDOW-CHECKS-001. A RANGE frame with an offset bound needs
 * exactly one ORDER BY column, and a GROUPS frame needs an ORDER BY. A
 * specification that refines an existing window may take its ordering from
 * that window, which the WINDOW clause defines; such a specification is left
 * to the clause. Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS,
 * `transformWindowDefinitions` in `src/backend/parser/parse_clause.c` of PostgreSQL 17.
 * Termination: constant work. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class WindowChecks
{
    /**
     * Reports a frame its specification's ordering cannot support.
     */
    public function specification(Derivation $derivation, WindowSpecification $window): void
    {
        $frame = $window->frame;
        if ($frame === null || $window->existing !== null) {
            return;
        }
        $offset = $frame->start->kind->offset() || ($frame->end?->kind->offset() ?? false);
        if ($frame->mode === FrameMode::Range && $offset && count($window->order) !== 1) {
            $derivation->report(new WindowProblem(WindowProblemKind::RangeOffsetOrder));
        }
        if ($frame->mode === FrameMode::Groups && $window->order === []) {
            $derivation->report(new WindowProblem(WindowProblemKind::GroupsWithoutOrder));
        }
    }
}
