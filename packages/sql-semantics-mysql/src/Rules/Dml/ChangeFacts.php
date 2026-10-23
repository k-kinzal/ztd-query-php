<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the facts of UPDATE and DELETE.
 *
 * Rule: MYSQL-UPDATE-001 and MYSQL-DELETE-001. The common tables of WITH
 * are bound first and are visible in every query of the statement. The
 * table references are derived as a FROM clause is (MYSQL-FROM-001), each
 * later reference seeing the earlier ones for LATERAL. The columns of the
 * assignments, their values, WHERE and ORDER BY see every table of the
 * references; the value DEFAULT is the default of its column; LIMIT sees
 * none. An UPDATE whose references hold more than one table is a
 * multiple-table UPDATE, which takes neither ORDER BY nor LIMIT. A single-
 * table DELETE sees its table under its correlation name, or its table name
 * when it has none. Each table a multiple-table DELETE deletes from must
 * name a table of its references. A column an UPDATE assigns, and a table
 * a multiple-table DELETE deletes from, must belong to a table or view; a
 * derived table, a table function or a common table is not updatable
 * (ER_NON_UPDATABLE_TABLE), which an UPDATE of that one table reports
 * before it resolves its assignments (verified on a live 8.4 server); a generated column takes only DEFAULT
 * (MYSQL-GENERATED-WRITE-001). The statements return no rows.
 * Terminates: one pass over the finite parts. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/update.html,
 * https://dev.mysql.com/doc/refman/8.4/en/delete.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ChangeFacts
{
    /**
     * Derives UPDATE.
     */
    public function update(Update|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate|\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update\ConflictingUpdate $update, Derivation $derivation, Environment $outer): void
    {
        if (($sampling = (new Read\Sampling())->of($update)) !== null) {
            $derivation->report($sampling);
        }
        $base = $this->base($update->with, $derivation, $outer);
        $visible = $this->references($update->tables, $derivation, $base);
        $environment = new Environment($derivation->context, $base, $visible);
        $single = count($visible) === 1 && !$this->updatable($visible[0]->relation, $derivation);
        if ($single) {
            $derivation->report(new WriteMisuse(WriteRule::NonUpdatableTarget, $this->label($visible[0]->relation)));
        }
        $this->clauses($update->where, [], null, $derivation, $base, $environment);
        $fields = (new \SqlSemantics\Platform\MySql\Rules\Dml\Update\AssignmentFacts())->derive($update->assignments, $derivation, $environment);
        (new GeneratedWrites())->assignments($update->assignments, $fields, $derivation);
        foreach ($single ? [] : $fields as $field) {
            if ($field->resolution instanceof ResolvedColumn && !$this->updatable($field->resolution->relation, $derivation)) {
                $merged = $this->merged($field->resolution->relation, $field->resolution->slot->name->value ?? '', $derivation);
                $derivation->report($merged ?? new WriteMisuse(WriteRule::NonUpdatableTarget, $this->label($field->resolution->relation)));
            }
        }
        $this->clauses(null, $update->orderBy, $update->limit, $derivation, $base, $environment);
        if (count($visible) > 1 && $update->orderBy !== []) {
            $derivation->report(new WriteMisuse(WriteRule::OrderedMultipleUpdate));
        }
        if (count($visible) > 1 && $update->limit !== null) {
            $derivation->report(new WriteMisuse(WriteRule::LimitedMultipleUpdate));
        }
    }

    /**
     * Derives a single-table DELETE.
     */
    public function delete(Delete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete $delete, Derivation $derivation, Environment $outer): void
    {
        if (($sampling = (new Read\Sampling())->of($delete)) !== null) {
            $derivation->report($sampling);
        }
        $base = $this->base($delete->with, $derivation, $outer);
        $fact = $derivation->relation($delete->table, $base);
        $environment = new Environment($derivation->context, $base, [new VisibleRelation($delete->table, $fact->shape, $delete->table->alias, $delete->table->name, [], (new TableShapes())->implicit($fact))]);
        $this->clauses($delete->where, $delete->orderBy, $delete->limit, $derivation, $base, $environment);
    }

    /**
     * Derives a multiple-table DELETE.
     */
    public function deleteMultiple(MultipleDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete $delete, Derivation $derivation, Environment $outer): void
    {
        if (($sampling = (new Read\Sampling())->of($delete)) !== null) {
            $derivation->report($sampling);
        }
        $base = $this->base($delete->with, $derivation, $outer);
        $visible = $this->references($delete->tables, $derivation, $base);
        $this->targets($delete->targets, $visible, $derivation, $delete);
        $this->clauses($delete->where, [], null, $derivation, $base, new Environment($derivation->context, $base, $visible));
    }

    /**
     * Resolves the ordered target namespace independently of statement publication.
     *
     * @param list<QualifiedName> $targets The requested deletion targets
     * @param list<VisibleRelation> $visible The complete input namespace
     */
    public function targets(array $targets, array $visible, Derivation $derivation, MultipleDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete|null $statement = null): void
    {
        $projection = new Projection();
        foreach ($targets as $target) {
            $found = null;
            foreach ($visible as $relation) {
                if ($target->schema !== null && $derivation->facts()->relation($relation->relation)->table instanceof CommonTable) {
                    continue;
                }
                $name = $relation->alias === null ? new QualifiedName($target->name, $target->schema ?? $derivation->context->searchPath[0]) : $target;
                $found ??= $projection->admits($derivation, $relation, $name) ? $relation : null;
            }
            if ($found === null) {
                $derivation->report(new UnknownDeleteTable($target));
            } elseif (!$this->updatable($found->relation, $derivation)) {
                $derivation->report(new WriteMisuse(WriteRule::NonUpdatableTarget, $this->label($found->relation), $statement));
            }
            if ($found !== null) {
                $derivation->bindReference($target, $found->relation);
            }
        }
    }

    /**
     * Tells whether rows of a relation occurrence can be changed: a table or view, not a derived table, a table function or a common table.
     */
    public function updatable(Relation $relation, Derivation $derivation): bool
    {
        if ($relation instanceof DerivedTable || $relation instanceof JsonTable) {
            return false;
        }

        return !$derivation->facts()->relation($relation)->table instanceof CommonTable;
    }

    /**
     * Answers the problem MySQL 5.7 finds in assigning a column of a derived table it merges, or null for another relation or release.
     *
     * The column is not updatable when its select item is not a column (ER_NONUPDATEABLE_COLUMN);
     * otherwise the target is the first table of the derived query, through the derived tables it
     * merges in turn, named by its alias (verified on a live 5.7.44 server).
     */
    public function merged(Relation $relation, string $column, Derivation $derivation): ?WriteMisuse
    {
        $materialization = new \SqlSemantics\Platform\MySql\Rules\Typing\Materialization();
        if ($derivation->context->profile->grammar !== \SqlSemantics\Contract\GrammarRelease::MySql5744 || !$relation instanceof DerivedTable || !$materialization->mergeable($relation->query, $derivation->context->profile->grammar, $derivation->facts(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->derivedMerge)) {
            return null;
        }
        $select = $this->block($relation->query);
        if ($select !== null && $this->computed($select, $column)) {
            return new WriteMisuse(WriteRule::NonUpdatableColumn, new Name($column));
        }
        $first = $this->leading($select?->from, $derivation->facts());

        return $first === null ? null : new WriteMisuse(WriteRule::NonUpdatableTarget, $this->label($first));
    }

    /**
     * Tells whether the select item that names a column of a query block is not a column.
     *
     * An item is named by its alias, or by the column it is, through parentheses. The name is
     * compared without regard to case. An item that is a column, or a name no item has, is not
     * computed.
     */
    public function computed(\SqlSemantics\Platform\MySql\Statement\Query\Select $select, string $column): bool
    {
        foreach ($select->items as $item) {
            if (!$item instanceof \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression) {
                continue;
            }
            $expression = $item->expression;
            while ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
                $expression = $expression->operand;
            }
            $name = $item->alias->value ?? ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse ? $expression->name->value : null);
            if ($name !== null && strcasecmp($name, $column) === 0 && !$expression instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the first table of a FROM clause, or null when it starts with another relation.
     *
     * The first table is found through table lists, the left side of joins, parentheses, escaped
     * references, and the derived tables MySQL 5.7 merges.
     */
    public function leading(?Relation $from, ?\SqlSemantics\Statement\Fact\Facts $facts = null): ?NamedRelation
    {
        $materialization = new \SqlSemantics\Platform\MySql\Rules\Typing\Materialization();
        while ($from !== null && !$from instanceof NamedRelation) {
            $from = match (true) {
                $from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableList => $from->members[0] ?? null,
                $from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable => $from->left,
                $from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation, $from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation => $from->relation,
                $from instanceof DerivedTable && $materialization->mergeable($from->query, \SqlSemantics\Contract\GrammarRelease::MySql5744, $facts) => $this->block($from->query)?->from,
                default => null,
            };
        }

        return $from instanceof NamedRelation ? $from : null;
    }

    /**
     * Answers the query block a query is, through parentheses and a query expression without ordering, or null.
     */
    public function block(\SqlSemantics\Statement\Query $query): ?\SqlSemantics\Platform\MySql\Statement\Query\Select
    {
        while ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
            $query = $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery ? $query->query : $query->body;
        }

        return $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select ? $query : null;
    }

    /**
     * Answers the name the server gives a relation occurrence in its messages: its correlation name, or its table name when it has none.
     */
    public function label(Relation $relation): ?Name
    {
        return match (true) {
            $relation instanceof NamedRelation => $relation->alias() ?? $relation->name()->name,
            $relation instanceof DerivedTable, $relation instanceof JsonTable => $relation->alias,
            default => null,
        };
    }

    /**
     * Binds the common tables of WITH, when there is one.
     */
    public function base(?WithClause $with, Derivation $derivation, Environment $outer): Environment
    {
        return $with === null ? $outer : $with->bind($derivation, $outer);
    }

    /**
     * Derives the table references, reports a name two of them share, and answers the relations they make visible.
     *
     * @param list<Relation> $tables
     * @return list<VisibleRelation>
     */
    public function references(array $tables, Derivation $derivation, Environment $base): array
    {
        $visible = [];
        $from = new FromScope();
        foreach ($tables as $table) {
            array_push($visible, ...$from->open($table, $derivation, $base, $visible)->visible);
        }
        $from->unique($visible, $derivation);

        return $visible;
    }

    /**
     * Derives WHERE and ORDER BY where the tables are visible, and LIMIT where they are not.
     *
     * @param list<OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> $orderBy
     */
    public function clauses(Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null $where, array $orderBy, ?Limit $limit, Derivation $derivation, Environment $base, Environment $environment): void
    {
        if ($where !== null) {
            (new \SqlSemantics\Platform\MySql\Rules\Dml\Update\ClauseFacts())->derive($where, $derivation, $environment);
        }
        foreach ($orderBy as $item) {
            \SqlSemantics\Diagnostic\Check::invariant(!$item->expression instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal, 'DML ordering retains expressions rather than result positions.');
            (new \SqlSemantics\Platform\MySql\Rules\Dml\Update\ClauseFacts())->derive($item->expression, $derivation, $environment);
            $position = (new Read\Ordering())->position($item->expression, in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true));
            if ($position !== null) {
                $derivation->report($position);
            }
        }
        (new TailFacts())->limit($limit, $derivation, $base);
    }
}
