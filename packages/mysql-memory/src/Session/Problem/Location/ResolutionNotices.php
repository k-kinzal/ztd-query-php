<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use LogicException;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use WeakMap;

/**
 * Publishes semantic resolution notices only after their subjects are reached.
 *
 * Tables open before these notices; an earlier operand failure prevents a later
 * notice. Prepared execution and cached stored instructions do not prepare again
 * until their relation identities change. Native VALUES observations establish
 * this order, including missing relations, columns and repeated procedure calls.
 *
 * @visibility MySqlMemory
 */
final class ResolutionNotices
{
    /**
     * @param list<int>|null $failure The first unresolved operand in resolution order
     * @throws LogicException When a resolution notice has no semantic subject
     */
    public function report(MySqlStatement|QueryPreparation $operation, Session $session, bool $prepared, ?array $failure): void
    {
        if (($session->replayed && !$prepared) || !$this->fresh($operation, $session, $failure === null)) {
            return;
        }
        $locator = (new Locator($session->settings()->release() === GrammarRelease::MySql8044, $session->settings()->release(), $prepared))->statement($operation instanceof MySqlStatement ? $operation : $operation->statement);
        $pending = [];
        foreach (($operation instanceof MySqlStatement ? $operation->statementInformation() : $operation->facts)->warnings as $warning) {
            if (!$warning instanceof Deprecation || $warning->phase() !== NoticePhase::Resolution) {
                continue;
            }
            $subject = ($operation instanceof MySqlStatement ? $operation->statementInformation() : $operation->facts)->warningSubject($warning);
            if (!$subject instanceof InsertedColumn) {
                throw new LogicException('A VALUES resolution warning requires its semantic operand.');
            }
            $place = $locator->place($subject->column);
            if ($place !== null && ($failure === null || Locator::precedes($place[1], $failure))) {
                $pending[] = [$warning, $place[1]];
            }
        }
        usort($pending, static fn (array $a, array $b): int => $a[1] === $b[1] ? 0 : (Locator::precedes($a[1], $b[1]) ? -1 : 1));
        foreach ($pending as [$warning]) {
            $session->diagnostics->warning($warning->code(), $warning->message());
        }
    }

    /**
     * Retains successful stored preparations while preserving retries after failure.
     */
    public function fresh(MySqlStatement|QueryPreparation $operation, Session $session, bool $successful): bool
    {
        if ($session->program === null) {
            return true;
        }
        $tables = [];
        foreach ((new Walker())->find($operation instanceof MySqlStatement ? $operation : $operation->statement, NamedRelation::class) as $relation) {
            $resolved = $relation->hasBinding() ? $relation->relationInformation()->table : null;
            $tables[] = $resolved instanceof DeclaredTable ? $resolved->table : null;
        }
        $cache = $session->preparation->resolutionNotices ??= new WeakMap();
        $previous = $cache[$operation instanceof MySqlStatement ? $operation : $operation->statement] ?? null;
        if ($successful) {
            $cache[$operation instanceof MySqlStatement ? $operation : $operation->statement] = $tables;
        } else {
            unset($cache[$operation instanceof MySqlStatement ? $operation : $operation->statement]);
        }
        return $previous === null || $previous !== $tables;
    }
}
