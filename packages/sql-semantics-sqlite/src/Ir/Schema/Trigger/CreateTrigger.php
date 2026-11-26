<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerActions;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaise;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved SQLite row trigger with an ordered mutation program.
 *
 * @visibility public
 * @example Emitting a resolved trigger definition
 *     $dialect = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $dialect->context(['CREATE TABLE t (a)', 'CREATE TABLE audit (a)']);
 *     $trigger = $dialect->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO audit (a) VALUES (NEW.a); END', $declarations);
 *     $trigger->toString() // => 'CREATE TRIGGER "main"."tr" AFTER INSERT ON "main"."t" BEGIN INSERT INTO "audit" ("a") VALUES (NEW."a"); END'
 */
final class CreateTrigger implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TriggerInsert|TriggerUpdate|TriggerDelete|TriggerRaise> The actions in native execution order
     */
    public readonly array $program;

    /**
     * @var list<TriggerUpdateName> The column selectors in declaration order
     */
    public readonly array $selectors;

    /**
     * Validates the absolute trigger placement and all composed program references.
     * @param list<TriggerInsert|TriggerUpdate|TriggerDelete|TriggerRaise> $program The trigger actions
     * @param list<TriggerUpdateName> $selectors The UPDATE OF selectors, when supplied
     * @throws InvalidConstruction When the trigger composition cannot be represented faithfully
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $name,
        public readonly TriggerPlacement $placement,
        public readonly TableSource $watched,
        public readonly TriggerTiming $timing,
        public readonly TriggerEvent $event,
        array $program,
        array $selectors = [],
        public readonly ?TriggerCondition $condition = null,
        public readonly bool $ifNotExists = false,
    ) {
        $checks = new TriggerChecks();
        $checks->placement($schema, $name, $placement, $watched, $timing);
        $actions = (new TriggerActions())->copy($program);
        $columns = Check::listOf($selectors, TriggerUpdateName::class, 'A trigger needs an ordered list of UPDATE OF selectors.');
        $checks->selectors($columns, $event, $watched);
        if ($condition !== null) {
            $checks->field($condition->field, $event, $watched);
        }
        $checks->program($actions, $placement, $watched, $event);
        $this->program = $actions;
        $this->selectors = $columns;
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the trigger and its checked native body.
     */
    public function toString(): string
    {
        return (new TriggerSql())->statement($this);
    }
}
