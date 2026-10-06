<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnChanges;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\StandaloneCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TrailingCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER [IGNORE] TABLE t action, …`: a request to change the structure of a table.
 *
 * Mirrors PT_alter_table_stmt and PT_alter_table_standalone_stmt. Rule:
 * MYSQL-ALTER-TABLE-001. The actions are kept in the order written,
 * modifiers (ALGORITHM, LOCK, VALIDATION) among them; a standalone action
 * (a partition or tablespace operation) is the last one and only modifiers
 * precede it; `PARTITION BY` and `REMOVE PARTITIONING` are the last one and
 * follow the others without a comma. The table resolves by
 * MYSQL-CHANGE-TARGET-001; a declared view is refused (MYSQL-RELATION-KIND-001). The statement node is the relation occurrence the
 * expressions of the actions see: its relation fact holds the resolution of
 * the table and the row shape of the visible columns the table has after
 * the column actions; its invisible columns are found by name only
 * (MYSQL-COLUMN-CHANGES-001), which also reports a changed, dropped,
 * renamed or altered column the completely known table does not have and a
 * column name the table would have twice. The statement changes no
 * declaration and provides none. IGNORE exists in 5.6 only.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html,
 * https://dev.mysql.com/doc/refman/5.6/en/alter-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the actions of a table change
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT)');
 *     $alter = $semantics->analyze('ALTER TABLE t DROP COLUMN b, ALGORITHM = INPLACE', [$table]);
 *     [count($alter->statement->commands), $alter->facts->diagnostics[0]->message()] // => [2, 'Column b does not exist in the table.']
 */
final class AlterTable implements Statement, Relation
{
    use Snapshot;

    /**
     * @var list<AlterCommand> The actions in the order written
     */
    public readonly array $commands;

    /**
     * @param QualifiedName $table The table with its optional database
     * @param list<AlterCommand> $commands The actions in the order written
     * @param bool $ignore Whether IGNORE is written (5.6)
     */
    public function __construct(public readonly QualifiedName $table, array $commands, public readonly bool $ignore = false)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
        $this->commands = Check::listOf($commands, AlterCommand::class, 'ALTER TABLE holds a list of actions.');
        $last = count($this->commands) - 1;
        foreach ($this->commands as $index => $command) {
            Check::input(!$command instanceof TrailingCommand || $index === $last, 'PARTITION BY and REMOVE PARTITIONING are the last action.');
            Check::input(!$command instanceof StandaloneCommand || $index === $last, 'A partition or tablespace operation is the last action.');
            Check::input(
                $index === $last || $command instanceof AlterModifier || !$this->commands[$last] instanceof StandaloneCommand,
                'Only modifiers precede a partition or tablespace operation.',
            );
        }
    }

    /**
     * Derives the table and every action in the scope of the table as the statement leaves it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $original = (new Targets())->target($derivation, $this->table);
        (new RelationKinds())->require($derivation, $this->table, $original->table, RelationKind::BaseTable);
        $changes = new ColumnChanges();
        $fact = $derivation->target($this, new RelationFact($changes->apply($original, $this->commands, $derivation, true), $original->table));
        $scope = new Environment($derivation->context, null, [new VisibleRelation($this, $fact->shape, null, $this->table, [], $changes->implicit())]);
        foreach ($this->commands as $command) {
            $command->deriveCommand($derivation, $scope);
        }
    }

    /**
     * Resolves the table and answers the row shape of its visible columns after the column actions.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $original = (new Targets())->target($derivation, $this->table);

        return new RelationFact((new ColumnChanges())->apply($original, $this->commands, $derivation, false), $original->table);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        if ($this->ignore) {
            $out->keyword('IGNORE');
        }
        $out->keyword('TABLE');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
        foreach ($this->commands as $index => $command) {
            if ($index > 0 && !$command instanceof TrailingCommand) {
                $out->symbol(',');
            }
            $out->node($command);
        }
    }
}
