<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Resolution\TriggerNamespace;
use SqlSemantics\Platform\Sqlite\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTiming;

/**
 * Derives the facts of a trigger definition.
 *
 * Rule: SQLITE-TRIGGER-SCOPE-001. The watched table is resolved once. The
 * WHEN condition and every statement of the program see, under the
 * qualifiers NEW and OLD only, a row of the watched table with its implicit
 * columns: NEW in INSERT and UPDATE triggers, OLD in UPDATE and DELETE
 * triggers. An unknown UPDATE OF name is inactive until a matching column exists.
 * A BEFORE or AFTER trigger, BEFORE being the default, on a view
 * and an INSTEAD OF trigger on a table are reported
 * (SQLITE-RELATION-KIND-001). In the program, a written table qualified with a schema, an
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
        $names = new TriggerNamespace();
        $owner = $names->owner($derivation->context, $trigger->name->schema, $trigger->temporary, $trigger->table->name);
        $watched = $names->watched($trigger->table->name, $owner);
        $tables = new TableShapes();
        $fact = $derivation->target($trigger->table, $tables->fact($derivation, $watched, $derivation->environment()));
        (new RelationKinds())->refuse($fact, match ($trigger->timing) {
            TriggerTiming::InsteadOf => KindRefusal::InsteadOfTrigger,
            TriggerTiming::After => KindRefusal::AfterTrigger,
            TriggerTiming::Before, null => KindRefusal::BeforeTrigger,
        }, $derivation);
        $implicit = $tables->implicit($fact);
        $rows = [];
        foreach (['new' => $trigger->event !== TriggerEvent::Delete, 'old' => $trigger->event !== TriggerEvent::Insert] as $qualifier => $available) {
            if ($available) {
                $rows[] = new VisibleRelation($trigger->table, $fact->shape, new Name($qualifier), null, [ColumnResolver::QUALIFIED_ONLY], $implicit);
            }
        }
        $schema = Comparison::AsciiInsensitive->equal($owner->value, 'temp') ? null : $owner;
        $environment = new Environment($derivation->context, relations: $rows, relationSchema: $schema);
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
