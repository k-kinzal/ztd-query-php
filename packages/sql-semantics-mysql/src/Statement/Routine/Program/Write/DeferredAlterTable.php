<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnChanges;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Column;
use SqlSemantics\Platform\MySql\Statement\Alter\Command;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\StandaloneCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TrailingCommand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete ALTER instruction retained until it is invoked.
 *
 * The target, all ordered actions and column declarations remain available to
 * binding and invocation. Ordinary ALTER values exclude this deferred column role.
 *
 * @visibility public
 * @example Retaining all actions of a stored alteration
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '5.7.44');
 *     $operation = $semantics->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER())');
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable // => true
 */
final class DeferredAlterTable implements ProgramStatement, Relation
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
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($table);
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
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Name\ColumnQualifiers())->check($table, $this->commands);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\TableChange\AlterationRoles())->deferred($this->commands), 'A deferred ALTER instruction retains an intrinsic invocation-time condition.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the table and every action in the scope of the table as the statement leaves it.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        if ($this->ignore) {
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::AlterIgnore, $derivation);
        }
        $original = (new Targets())->target($derivation, $this->table);
        (new RelationKinds())->require($derivation, $this->table, $original->table, RelationKind::BaseTable);
        $changes = new ColumnChanges();
        $fact = $derivation->target($this, new RelationFact($changes->apply($original, $this->commands, $derivation, true), $original->table));
        $scope = new Environment($derivation->context, null, [new VisibleRelation($this, $fact->shape, null, $this->table, [], $changes->implicit())]);
        foreach ($this->commands as $command) {
            if ($command instanceof Partition\ExchangePartition && (new Targets())->same($derivation->context, $this->table, $command->table)) {
                $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable($command->table->name));
            }
            $command->deriveCommand($derivation, $scope);
            if ($command instanceof Partition\MaintainPartitions && $command->kind->reports()) {
                (new \SqlSemantics\Platform\MySql\Rules\Server\AdminRows())->admin($derivation);
            }
        }
        foreach ($this->commands as $command) {
            $columns = match (true) {
                $command instanceof Column\AddColumn => [$command->column],
                $command instanceof Column\ChangeColumn => [$command->definition],
                $command instanceof Column\AddColumns => $command->elements,
                default => [],
            };
            foreach ($columns as $column) {
                if ($column instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition) {
                    (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->check($column->specification->dataType(), $column->name->column->value, $derivation);
                }
            }
        }
        if (!in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            foreach ($this->commands as $command) {
                if ($command instanceof Command\ConvertCharset) {
                    $command->deriveCollation($derivation, $this);
                }
            }
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
