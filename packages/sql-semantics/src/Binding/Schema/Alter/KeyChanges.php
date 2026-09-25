<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Alter;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\ColumnAlterations;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\KeyAlterations;
use SqlSemantics\Binding\Statement\Definition\Relation\ConstraintActions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind;
use SqlSemantics\Model\Definition\Relation\Constraint\AddConstraint;
use SqlSemantics\Model\Definition\Relation\Constraint\AddIndexConstraint;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;

/**
 * Applies the table-level key, constraint, and index actions of ALTER TABLE to a table snapshot: MySQL ADD and DROP
 * of PRIMARY KEY, UNIQUE, FOREIGN KEY, CHECK, and indexes, and PostgreSQL ADD and DROP CONSTRAINT. Both servers drop
 * before they add within one statement, so a drop applies where it is written and an addition waits for {@see self::add()}.
 * @visibility SqlSemantics
 */
final class KeyChanges
{
    /**
     * Holds the constraints and indexes of the altered table and the actions that add to them.
     * @param list<TableConstraint> $constraints
     * @param list<IndexDefinition> $indexes
     * @param list<Node> $additions ADD actions waiting until the drops of the statement are applied
     */
    public function __construct(public readonly TableDefinition $table, public readonly array $constraints, public readonly array $indexes, public readonly array $additions = [])
    {
    }

    /**
     * Records an ADD of a key, constraint, or index, or applies a DROP of one; null for an action that changes none.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public function action(Node $action, Scope $scope, QueryContext $context): ?self
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $action->tokens());
        $mysql = $scope->identifiers->dialect === Dialect::MySql;
        if ($words[0] === 'ADD' && Tree::child($action, $mysql ? ['table_constraint_def', 'key_def'] : ['TableConstraint']) !== null) {
            return new self($this->table, $this->constraints, $this->indexes, [...$this->additions, $action]);
        }
        if ($mysql && $action->name === 'alter_list_item' && $words[0] === 'DROP' && self::keyDrop($action)) {
            return $this->drop(KeyAlterations::kind($action), ColumnAlterations::name($action, $scope), $scope);
        }
        if (!$mysql && $action->name === 'alter_table_cmd' && array_slice($words, 0, 2) === ['DROP', 'CONSTRAINT']) {
            return $this->drop(KeyKind::Constraint, ConstraintActions::drop($action, $context)->name, $scope);
        }
        return null;
    }

    /**
     * Reports whether a MySQL DROP item addresses an index, a key, or a constraint rather than a column.
     */
    public static function keyDrop(Node $item): bool
    {
        $second = Tree::significant($item)[1] ?? null;
        return $second instanceof Node ? $second->name === 'key_or_index' : in_array(strtoupper($second->text ?? ''), ['FOREIGN', 'PRIMARY', 'CHECK', 'CONSTRAINT', 'INDEX', 'KEY'], true);
    }

    /**
     * Removes the addressed constraint and, for an index name, the index; PRIMARY KEY stands for the primary key.
     */
    public function drop(?KeyKind $kind, string $name, Scope $scope): self
    {
        $identifiers = $scope->identifiers;
        $constraints = array_values(array_filter($this->constraints, fn (TableConstraint $constraint): bool => !self::addressed($constraint, $kind) || ($kind !== null && !$identifiers->equal($this->name($constraint, $scope->identifiers->dialect), $name))));
        $indexes = $kind !== KeyKind::Index ? $this->indexes : array_values(array_filter($this->indexes, static fn (IndexDefinition $index): bool => !$identifiers->equal(self::indexName($index), $name)));
        return new self($this->table, $constraints, $indexes, $this->additions);
    }

    /**
     * Returns the name of an index; MySQL names an unnamed index after its first column.
     */
    public static function indexName(IndexDefinition $index): string
    {
        $first = $index->elements[0] ?? null;
        return $index->name ?? ($first instanceof \SqlSemantics\Schema\Index\ColumnKey ? ($first->column->columnBinding()?->column->name ?? $first->column->referenceParts()[0] ?? '') : '');
    }

    /**
     * Reports whether a constraint is of the kind a DROP addresses: DROP INDEX reaches keys, DROP CONSTRAINT any constraint.
     */
    public static function addressed(TableConstraint $constraint, ?KeyKind $kind): bool
    {
        return match ($kind) {
            null => $constraint instanceof Constraint\PrimaryKey,
            KeyKind::Index => $constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey,
            KeyKind::ForeignKey => $constraint instanceof Constraint\ForeignKey,
            KeyKind::Check => $constraint instanceof Constraint\Check,
            KeyKind::Constraint => true,
        };
    }

    /**
     * Returns the name the server gives a constraint: MySQL names a primary key PRIMARY and an unnamed unique key after
     * its first column; PostgreSQL names an unnamed key table_pkey, table_columns_key, or table_columns_fkey.
     */
    public function name(TableConstraint $constraint, Dialect $dialect): string
    {
        if ($dialect === Dialect::MySql) {
            if ($constraint instanceof Constraint\PrimaryKey) {
                return 'PRIMARY';
            }
            return $constraint instanceof Constraint\UniqueKey ? $constraint->index->name ?? $constraint->name ?? $constraint->localColumns()[0] ?? '' : $constraint->name ?? '';
        }
        $suffix = match ($constraint->kind) {
            ConstraintKind::PrimaryKey => 'pkey',
            ConstraintKind::Unique => 'key',
            ConstraintKind::ForeignKey => 'fkey',
            ConstraintKind::Check => null,
        };
        $columns = $constraint->kind === ConstraintKind::PrimaryKey ? [] : $constraint->localColumns();
        return $constraint->name ?? ($suffix === null ? '' : implode('_', [$this->table->name, ...$columns, $suffix]));
    }

    /**
     * Applies the recorded additions after the drops; PostgreSQL adopts the index a key names with USING INDEX.
     * @return array{list<TableConstraint>, list<IndexDefinition>}
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public function add(Scope $scope, QueryContext $context): array
    {
        $constraints = $this->constraints;
        $indexes = $this->indexes;
        $mysql = $scope->identifiers->dialect === Dialect::MySql;
        $keys = new KeyAlterations($this->table->schema, $this->table->schema === '' ? [$this->table->name] : [$this->table->schema, $this->table->name]);
        foreach ($this->additions as $action) {
            if ($mysql) {
                array_push($constraints, ...$keys->constraints($action, $scope));
                array_push($indexes, ...$keys->indexes($action, $scope));
                continue;
            }
            $added = ConstraintActions::add($action, $scope, $context);
            if ($added instanceof AddConstraint) {
                $constraints[] = $added->constraint;
            } elseif ($added instanceof AddIndexConstraint) {
                [$constraints, $indexes] = self::adopt($added, $action, $constraints, $indexes, $scope);
            }
        }
        if (count(array_filter($constraints, static fn (TableConstraint $constraint): bool => $constraint instanceof Constraint\PrimaryKey)) > 1) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::MultiplePrimaryKeys, $this->additions[0] ?? $this->table->source);
        }
        return [$constraints, $indexes];
    }

    /**
     * Turns the named index into the added primary key or unique constraint, which takes over the index.
     * @param list<TableConstraint> $constraints
     * @param list<IndexDefinition> $indexes
     * @return array{list<TableConstraint>, list<IndexDefinition>}
     */
    public static function adopt(AddIndexConstraint $added, Node $source, array $constraints, array $indexes, Scope $scope): array
    {
        foreach ($indexes as $position => $index) {
            if ($index->name === null || !$scope->identifiers->equal($index->name, $added->index) || $index->elements === []) {
                continue;
            }
            unset($indexes[$position]);
            $name = $added->name ?? $added->index;
            $constraints[] = $added->kind === ConstraintKind::PrimaryKey
                ? new Constraint\PrimaryKey($index->elements, $added->checking, name: $name, source: $source)
                : new Constraint\UniqueKey($index->elements, $added->checking, $index->properties->nullsDistinct, name: $name, source: $source);
            break;
        }
        return [$constraints, array_values($indexes)];
    }
}
