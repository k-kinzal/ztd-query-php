<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use WeakMap;

/**
 * Tracks repeated preparation notices for retained stored selections.
 *
 * Initial invocation consumes the stored definition's syntax notices. An operand
 * failure causes preparation again on the next invocation. Opening failures keep
 * their relation snapshot; a changed declaration also requests preparation.
 * The cache contains semantic identities, never SQL or parser nodes.
 *
 * @visibility MySqlMemory
 */
final class SelectionNotices
{
    /**
     * Captures relation identities and emits notices only for another preparation.
     */
    public function start(DeferredSelection $selection, Facts $facts, Session $session): void
    {
        $cache = $session->preparation->selections ??= new WeakMap();
        $tables = [];
        foreach ((new Walker())->find($selection, NamedRelation::class) as $relation) {
            $resolved = $facts->covers($relation) ? $facts->relation($relation)->table : null;
            $tables[] = $resolved instanceof DeclaredTable ? $resolved->table : null;
        }
        $previous = $cache[$selection] ?? null;
        $cache[$selection] = [$tables, false];
        if ($previous === null || (!$previous[1] && $previous[0] === $tables)) {
            return;
        }
        foreach ($facts->warnings as $warning) {
            if ($warning instanceof Deprecation && $warning->construct === Deprecated::BinaryOperator) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
    }

    /**
     * Marks the reached operand failure after all input relations have opened.
     */
    public function failed(DeferredSelection $selection, Session $session): void
    {
        $cache = $session->preparation->selections;
        if ($cache !== null && isset($cache[$selection])) {
            $cache[$selection] = [$cache[$selection][0], true];
        }
    }
}
