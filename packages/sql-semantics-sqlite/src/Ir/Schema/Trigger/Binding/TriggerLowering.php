<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerPlacement;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerTiming;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerUpdateName;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Resolution\TriggerNamespace;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete as ParsedTriggerDelete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update as ParsedTriggerUpdate;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger as ParsedTrigger;

/**
 * Publishes trigger definitions with absolute storage, watched and program references.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerLowering
{
    /**
     * Lowers the checked trigger scope, event, selectors and ordered program.
     * @throws InvalidConstruction When a trigger role cannot be completed
     */
    public function lower(BoundStatement $operation, ParsedTrigger $statement): CreateTrigger
    {
        $context = $operation->context;
        Check::input(
            $context->relationNames === Comparison::AsciiInsensitive && $context->columnNames === Comparison::AsciiInsensitive,
            'A complete trigger requires native SQLite identifier comparison.'
        );
        Check::input(!$statement->temporary || $statement->name->schema === null, 'A TEMP keyword trigger cannot have a qualified name.');
        $owner = $this->schema($context, (new TriggerNamespace())->owner($context, $statement->name->schema, $statement->temporary, $statement->table->name));
        Check::input(
            $statement->name->schema !== null || $statement->table->name->schema !== null || $context->complete || $owner->key() === 'temp',
            'An unqualified trigger and watched relation need a known temporary namespace.'
        );
        $placement = match (true) {
            $statement->temporary => TriggerPlacement::TemporaryKeyword,
            $owner->key() === 'temp' => TriggerPlacement::NamedTemporary,
            default => TriggerPlacement::Stored,
        };
        $watched = $this->watched($operation, $statement);
        $event = TriggerEvent::from($statement->event->value);
        $timing = $statement->timing === null ? TriggerTiming::Before : TriggerTiming::from($statement->timing->value);
        $test = (new TriggerOperands())->condition($operation, $statement->table, $watched, $statement->when);
        $steps = $this->program($operation, $statement, $watched);

        return new CreateTrigger(
            $owner,
            new Identifier($statement->name->name->value),
            $placement,
            $watched,
            $timing,
            $event,
            $steps,
            $this->selectors($statement->columns, $watched),
            $test,
            $statement->ifNotExists
        );
    }

    /**
     * Lowers each supported action in written order.
     *
     * @return non-empty-list<TriggerInsert|TriggerUpdate|TriggerDelete>
     * @throws InvalidConstruction When the action has no checked trigger representation
     */
    public function program(BoundStatement $operation, ParsedTrigger $statement, TableSource $watched): array
    {
        $actions = [];
        $insert = new TriggerStepLowering();
        $mutation = new TriggerMutationLowering();
        foreach ($statement->steps as $step) {
            $actions[] = match (true) {
                $step instanceof InsertRows => $insert->lower($operation, $statement->table, $watched, $step),
                $step instanceof ParsedTriggerUpdate, $step instanceof ParsedTriggerDelete => $mutation->lower($operation, $statement->table, $watched, $step),
                default => throw new InvalidConstruction('A checked trigger body requires INSERT VALUES, UPDATE or DELETE actions.'),
            };
        }

        return $actions;
    }

    /**
     * Resolves the watched declaration and copies only its external identity and ordered slots.
     * @throws InvalidConstruction When the resolved watched relation lacks its required kind or shape
     */
    public function watched(BoundStatement $operation, ParsedTrigger $statement): TableSource
    {
        $resolved = $operation->facts->relation($statement->table)->table;
        Check::input($resolved instanceof DeclaredTable && $resolved->table->complete, 'A trigger needs a resolved complete watched relation.');
        $table = $resolved->table;
        $kind = match ($table->kind) {
            RelationKind::BaseTable => ExternalRelationKind::BaseTable,
            RelationKind::View => ExternalRelationKind::View,
            RelationKind::MaterializedView, RelationKind::ForeignTable, RelationKind::Sequence
                => throw new InvalidConstruction('A trigger needs an ordinary watched table or view.'),
        };
        $scope = $this->schema($operation->context, $table->name->schema ?? $operation->context->declarationSchema);
        $columns = [];
        foreach ($table->columns as $column) {
            $columns[] = new Identifier($column->name->value);
        }

        return new TableSource($scope, new Identifier($table->name->name->value), $columns, $kind);
    }

    /**
     * Resolves the written update selections while preserving known absent names.
     * @param list<Name> $names The parsed selectors in order
     * @return list<TriggerUpdateName>
     */
    public function selectors(array $names, TableSource $watched): array
    {
        $slots = array_map(static fn (Identifier $column): string => $column->key(), $watched->columns);
        $output = [];
        foreach ($names as $name) {
            $identifier = new Identifier($name->value);
            $match = array_search($identifier->key(), $slots, true);
            $output[] = new TriggerUpdateName($match === false ? $identifier : $match);
        }

        return $output;
    }

    /**
     * Resolves an exact native alias from the fixed schema path.
     * @throws InvalidConstruction When the schema name is unknown
     */
    public function schema(AnalysisContext $context, Name $written): Identifier
    {
        foreach ($context->searchPath as $schema) {
            if (Comparison::AsciiInsensitive->equal($schema->value, $written->value)) {
                return new Identifier($schema->value);
            }
        }

        throw new InvalidConstruction('A checked trigger requires a known schema qualifier.');
    }
}
