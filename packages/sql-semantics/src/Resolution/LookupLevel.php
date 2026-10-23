<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * The search of one query position for a column name.
 *
 * Rule: CORE-COLUMN-LOOKUP-001 (per-position part). A qualifier admits an
 * occurrence by its alias, or, without an alias, by its relation name and the
 * schema the qualifier writes. Within an admitted occurrence the declared
 * slots are searched before the implicit ones; an unqualified name skips the
 * hidden slots of either kind. An occurrence whose shape is
 * open, or which has a slot whose name depends on missing inputs, can still
 * own a name it does not visibly have.
 *
 * @visibility SqlSemantics
 */
final class LookupLevel
{
    /**
     * @var list<ResolvedColumn>
     */
    private array $found = [];

    /**
     * @var list<VisibleRelation>
     */
    private array $open = [];

    /**
     * @param Environment $scope The position searched
     * @param Name $column The column name
     * @param QualifiedName|null $qualifier The relation qualifier the use wrote
     * @param int $depth How many enclosing queries lie between the use and this position
     */
    public function __construct(Environment $scope, Name $column, ?QualifiedName $qualifier, int $depth)
    {
        $names = $scope->context->columnNames;
        foreach ($scope->relations as $relation) {
            if (!$this->admits($scope, $relation, $qualifier)) {
                continue;
            }
            $matches = [];
            foreach ($relation->shape->slots as $position => $slot) {
                if ($slot->name !== null && $names->equal($slot->name->value, $column->value) && ($qualifier !== null || !in_array($position, $relation->hidden, true))) {
                    $matches[] = new ResolvedColumn($relation->relation, $slot, $depth);
                }
            }
            if ($matches === []) {
                foreach ($relation->implicit as $index => $implicit) {
                    if ($qualifier === null && in_array(count($relation->shape->slots) + $index, $relation->hidden, true)) {
                        continue;
                    }
                    foreach ($implicit->names as $candidate) {
                        if ($names->equal($candidate->value, $column->value)) {
                            $matches = [new ResolvedColumn($relation->relation, $implicit->slot, $depth)];
                        }
                    }
                }
            }
            if ($matches === [] && self::undecided($relation) !== []) {
                $this->open[] = $relation;
            }
            array_push($this->found, ...$matches);
        }
    }

    /**
     * Answers the inputs that leave the names of an occurrence undecided: those of an open shape and of each unnamed slot.
     *
     * @return list<\SqlSemantics\Statement\Reference\Missing\MissingInput>
     */
    public static function undecided(VisibleRelation $relation): array
    {
        $missing = $relation->shape->missing;
        foreach ($relation->shape->slots as $slot) {
            array_push($missing, ...$slot->unnamed);
        }

        return $missing;
    }

    /**
     * Tells whether a qualifier admits an occurrence; no qualifier admits every occurrence.
     */
    public function admits(Environment $scope, VisibleRelation $relation, ?QualifiedName $qualifier): bool
    {
        if ($qualifier === null) {
            return true;
        }
        $names = $scope->context->relationNames;
        if ($relation->alias !== null) {
            return $qualifier->schema === null && $names->equal($relation->alias->value, $qualifier->name->value);
        }
        if ($relation->name === null || !$names->equal($relation->name->name->value, $qualifier->name->value)) {
            return false;
        }

        return $qualifier->schema === null || $relation->name->schema === null || $names->equal($relation->name->schema->value, $qualifier->schema->value);
    }

    /**
     * Answers the known slots the name denotes at this position.
     *
     * @return list<ResolvedColumn>
     */
    public function found(): array
    {
        return $this->found;
    }

    /**
     * Answers the incompletely known occurrences that can own the name at this position.
     *
     * @return list<VisibleRelation>
     */
    public function open(): array
    {
        return $this->open;
    }
}
