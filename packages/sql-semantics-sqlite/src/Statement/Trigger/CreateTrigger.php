<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Trigger;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\TriggerFacts;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;

/**
 * A request to create a trigger: a program of statements that runs when rows of a table change.
 *
 * Rule: SQLITE-CREATE-TRIGGER-001. The facts are derived by
 * SQLITE-TRIGGER-SCOPE-001. The statements of the program have the forms the
 * trigger grammar admits: a query, or an INSERT, UPDATE or DELETE without a
 * WITH clause and without a correlation name for the written table; an
 * UPDATE or DELETE has no RETURNING clause. The statement executes nothing
 * and provides no relation declaration.
 * Source: https://sqlite.org/lang_createtrigger.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateTrigger implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<Select|ValuesClause|Compound|WithQuery|InsertRows|InsertSelect|Update|Delete> The statements of the program in written order
     */
    public readonly array $steps;

    /**
     * @var list<Name> The columns of UPDATE OF in written order
     */
    public readonly array $columns;

    /**
     * @param QualifiedName $name The trigger name
     * @param TriggerEvent $event The change that fires the trigger
     * @param TriggerTable $table The watched table
     * @param list<Select|ValuesClause|Compound|WithQuery|InsertRows|InsertSelect|Update|Delete> $steps The statements of the program; at least one
     * @param TriggerTiming|null $timing The written timing
     * @param list<Name> $columns The columns of UPDATE OF
     * @param Scalar|null $when The condition of WHEN
     * @param bool $forEachRow Whether FOR EACH ROW is written
     * @param bool $temporary Whether TEMP or TEMPORARY is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @throws InvalidConstruction When a program statement is of no admitted form
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly TriggerEvent $event,
        public readonly TriggerTable $table,
        array $steps,
        public readonly ?TriggerTiming $timing = null,
        array $columns = [],
        public readonly ?Scalar $when = null,
        public readonly bool $forEachRow = false,
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
    ) {
        $list = (new ClosedList())->of($steps, [Select::class, ValuesClause::class, Compound::class, WithQuery::class, InsertRows::class, InsertSelect::class, Update::class, Delete::class], 'A trigger program consists of at least one query, INSERT, UPDATE or DELETE statement.', 1);
        foreach ($list as $step) {
            if ($step instanceof InsertRows || $step instanceof InsertSelect) {
                Check::input($step->into->with === null && $step->into->target->alias === null, 'An INSERT of a trigger program has no WITH clause and no correlation name.');
            } elseif ($step instanceof Update || $step instanceof Delete) {
                Check::input($step->with === null && $step->target->alias === null && $step->returning === [], 'An UPDATE or DELETE of a trigger program has no WITH clause, no correlation name and no RETURNING clause.');
            }
        }
        $this->steps = $list;
        $this->columns = Check::listOf($columns, Name::class, 'UPDATE OF names columns.');
        Check::input($columns === [] || $event === TriggerEvent::Update, 'Only an UPDATE trigger names columns.');
    }

    /**
     * Derives the watched table, the condition and the program.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new TriggerFacts())->derive($this, $derivation);
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->temporary) {
            $out->keyword('TEMP');
        }
        $out->keyword('TRIGGER');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Label);
        if ($this->timing !== null) {
            $out->keyword(...explode(' ', $this->timing->value));
        }
        $out->keyword($this->event->value);
        foreach ($this->columns as $position => $column) {
            $position === 0 ? $out->keyword('OF') : $out->symbol(',');
            $out->name($column, NameUse::Column);
        }
        $out->keyword('ON')->node($this->table);
        if ($this->forEachRow) {
            $out->keyword('FOR', 'EACH', 'ROW');
        }
        if ($this->when !== null) {
            $out->keyword('WHEN')->node($this->when);
        }
        $out->keyword('BEGIN');
        foreach ($this->steps as $step) {
            $out->node($step)->symbol(';');
        }
        $out->keyword('END');
    }
}
