<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerActionFields;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;

/**
 * Checks the dependencies that only a complete trigger can establish.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerChecks
{
    /**
     * Validates the native owner, watched kind, and timing.
     * @throws InvalidConstruction When the owner, watched identity or timing is incompatible
     */
    public function placement(
        Identifier $schema,
        Identifier $name,
        TriggerPlacement $placement,
        TableSource $watched,
        TriggerTiming $timing,
    ): void {
        $stored = $placement === TriggerPlacement::Stored;
        $owner = $schema->key();
        Check::input(
            $stored === ($owner !== 'temp') && (!$stored || $owner === $watched->schema->key()),
            'A stored trigger needs a watched relation in its owning schema and a temporary trigger belongs to temp.'
        );
        Check::input(
            !str_starts_with($name->key(), 'sqlite_') && !str_starts_with($watched->table->key(), 'sqlite_'),
            'A trigger name and watched relation cannot use the reserved SQLite prefix.'
        );
        Check::input(
            ($timing === TriggerTiming::InsteadOf) === ($watched->kind === ExternalRelationKind::View),
            'INSTEAD OF watches a view, and BEFORE and AFTER watch a table.'
        );
        Check::input($watched->columns !== [] && count($watched->columns) <= 2000, 'A watched relation needs its complete native column layout.');
    }

    /**
     * Resolves the membership of a child field in the activated event.
     * @throws InvalidConstruction When the field or row image is unavailable
     */
    public function field(TriggerField $field, TriggerEvent $event, TableSource $watched): void
    {
        $available = match ($event) {
            TriggerEvent::Insert => $field->image === RowImage::New,
            TriggerEvent::Update => true,
            TriggerEvent::Delete => $field->image === RowImage::Old,
        };
        Check::input(
            $available && isset($watched->columns[$field->column]),
            'A trigger field must belong to an available event image and declared watched position.'
        );
    }

    /**
     * Checks every ordered selector against the known watched declaration.
     * @param list<TriggerUpdateName> $selectors The written UPDATE OF names
     * @throws InvalidConstruction When a selector does not reflect a watched member or established absence
     */
    public function selectors(array $selectors, TriggerEvent $event, TableSource $watched): void
    {
        Check::input($selectors === [] || $event === TriggerEvent::Update, 'Only an UPDATE trigger can carry column selectors.');
        $names = [];
        foreach ($watched->columns as $column) {
            $names[] = $column->key();
        }
        foreach ($selectors as $item) {
            $column = $item->selector;
            Check::input(
                is_int($column) ? isset($watched->columns[$column]) : !in_array($column->key(), $names, true),
                'A trigger selector needs a declared column position or a known absent name.'
            );
        }
    }

    /**
     * Checks native destination binding and the scope of all row operand uses.
     * @param non-empty-list<TriggerInsert|TriggerUpdate|TriggerDelete> $program The ordered actions
     * @throws InvalidConstruction When the program contradicts its native scope or layout
     */
    public function program(array $program, TriggerPlacement $placement, TableSource $watched, TriggerEvent $event): void
    {
        $watchedIdentity = $this->identity($watched);
        $watchedLayout = $this->layout($watched);
        $stored = $placement === TriggerPlacement::Stored;
        $scopes = [];
        $destinations = [];
        foreach ($program as $step) {
            $table = $step->destination->table;
            $identity = $this->identity($table);
            $scope = $table->schema->key();
            $layout = $this->layout($table);
            $name = $table->table->key();
            Check::input(
                ($stored ? $scope === $watched->schema->key() : true) && ($scopes[$name] ?? $scope) === $scope,
                'An unqualified trigger destination needs one consistent native scope.'
            );
            Check::input(
                $identity !== $watchedIdentity || $layout === $watchedLayout,
                'A watched destination must agree with its declaration.'
            );
            $signature = [$layout, $step->destination->writable];
            Check::input(
                ($destinations[$identity] ?? $signature) === $signature,
                'Repeated references to a trigger destination must agree on its layout.'
            );
            $scopes[$name] = $scope;
            $destinations[$identity] = $signature;
            if ($step instanceof TriggerInsert) {
                $this->insertionImages($step, $event, $watched);
            } else {
                (new TriggerActionFields())->check($step, $event, $watched);
            }
        }
    }

    /**
     * Verifies the image uses of an INSERT VALUES action.
     */
    public function insertionImages(TriggerInsert $step, TriggerEvent $event, TableSource $watched): void
    {
        foreach ($step->fields as $field) {
            if ($field->value instanceof TriggerField) {
                $this->field($field->value, $event, $watched);
            }
        }
    }

    /**
     * Reads the exact ordered member names without object coercions.
     * @return list<string>
     */
    public function layout(TableSource $table): array
    {
        $names = [];
        foreach ($table->columns as $column) {
            $names[] = $column->value;
        }

        return $names;
    }

    /**
     * Builds an injective map key for a qualified table coordinate.
     */
    public function identity(TableSource $table): string
    {
        return $table->schema->key() . "\0" . $table->table->key();
    }
}
