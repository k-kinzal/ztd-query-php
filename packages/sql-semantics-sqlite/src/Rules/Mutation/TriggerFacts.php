<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives the facts of a trigger definition.
 *
 * Rule: SQLITE-TRIGGER-SCOPE-001. The watched table is resolved once. The
 * WHEN condition and every statement of the program see, under the
 * qualifiers NEW and OLD only, a row of the watched table with its implicit
 * columns: NEW in INSERT and UPDATE triggers, OLD in UPDATE and DELETE
 * triggers. A column of UPDATE OF that the table certainly lacks is
 * reported. In the program, a written table qualified with a schema, an
 * index choice and a bind parameter are reported, as SQLite rejects them
 * (SQLITE-PROGRAM-ONLY-001). Terminates: one pass
 * over the program.
 * Source: https://sqlite.org/lang_createtrigger.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerFacts
{
    /**
     * Derives every part of the trigger definition.
     */
    public function derive(CreateTrigger $trigger, Derivation $derivation): void
    {
        $fact = $derivation->relation($trigger->table, $derivation->environment());
        $implicit = (new TableShapes())->implicit($fact);
        $rows = [];
        foreach (['new' => $trigger->event !== TriggerEvent::Delete, 'old' => $trigger->event !== TriggerEvent::Insert] as $qualifier => $available) {
            if ($available) {
                $rows[] = new VisibleRelation($trigger->table, $fact->shape, new Name($qualifier), null, [ColumnResolver::QUALIFIED_ONLY], $implicit);
            }
        }
        $environment = new Environment($derivation->context, null, $rows);
        (new MutationScope())->names($trigger->columns, new VisibleRelation($trigger->table, $fact->shape, null, $trigger->table->name, [], $implicit), $derivation);
        (new ProgramOnly())->insideProgram([...($trigger->when === null ? [] : [$trigger->when]), ...$trigger->steps], $derivation);
        if ($trigger->when !== null) {
            $derivation->scalar($trigger->when, $environment);
        }
        foreach ($trigger->steps as $step) {
            if ($step instanceof InsertRows || $step instanceof InsertSelect || $step instanceof Update || $step instanceof Delete) {
                $target = $step instanceof Update || $step instanceof Delete ? $step->target : $step->into->target;
                if ($target->name->schema !== null) {
                    $derivation->report(new Misuse(MisuseRule::QualifiedTriggerTarget));
                }
                if ($target->index !== null) {
                    $derivation->report(new Misuse(MisuseRule::IndexedTriggerTarget));
                }
                $step->deriveWithin($derivation, $environment);
            } else {
                $derivation->query($step, $environment);
            }
        }
    }
}
