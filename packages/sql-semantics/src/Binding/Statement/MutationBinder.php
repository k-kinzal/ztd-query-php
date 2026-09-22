<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\FromBinder;
use SqlSemantics\Binding\ProjectionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\TableUse;

/**
 * Resolves write targets, assignment expressions, input queries, and returned rows.
 *
 * @visibility SqlSemantics
 */
final class MutationBinder
{
    /**
     * Shares statement identities and schema resolution with query inputs.
     */
    public function __construct(public readonly QueryContext $context)
    {
    }

    /**
     * Binds a mutation while keeping its result separate from its input query.
     */
    public function bind(Node $source, Node $statement): BoundStatement
    {
        $id = $this->context->ids->scope();
        $kind = StatementBinder::operation($statement);
        [$input, $targets] = $this->input($statement, $id, $kind);
        $scope = $input->scope ?? new Scope($this->context->tables->identifiers, $targets, queries: $this->context);
        $scope = $this->conflictScope($scope, $targets, $statement, $id);
        $writes = (new \SqlSemantics\Binding\Write\AssignmentBinder())->bind($statement, $scope, new Scope($scope->identifiers, $targets, queries: $this->context));
        if ($kind === 'UPDATE') {
            $targets = $this->updatedTargets($targets, $writes, $scope);
        }
        $conflicts = (new \SqlSemantics\Binding\Write\ConflictBinder())->bind($statement, $scope);
        $assignments = $this->assignments([...$writes, ...array_merge([], ...array_map(static fn ($conflict): array => $conflict->assignments, $conflicts))]);
        $whereNode = in_array($kind, ['INSERT', 'REPLACE'], true) ? null : (QueryNodes::local($statement, ['where_clause', 'opt_where_clause', 'where_or_current_clause', 'where_opt', 'where_opt_ret'])[0] ?? null);
        $where = (new \SqlSemantics\Binding\Write\ConflictBinder())->predicate($whereNode, $scope);
        $returning = QueryNodes::local($statement, ['returning_clause', 'where_opt_ret', 'upsert'])[0] ?? null;
        $outputs = $returning === null || !str_contains(strtoupper(Tree::text($returning)), 'RETURNING') ? [] : (new ProjectionBinder())->bind($returning, $scope);
        $queries = [];
        foreach (Tree::outer($statement, ['SelectStmt', 'query_expression', 'select', 'select_init', 'select_paren', 'insert_query_expression', 'create_select']) as $query) {
            $queries[] = $this->context->bind($query);
        }
        $values = (new ValuesBinder())->rows($statement, $scope);
        $insertion = null;
        if (in_array($kind, ['INSERT', 'REPLACE'], true)) {
            if ($targets === []) {
                Tree::invalid($statement, 'INSERT destination');
            }
            $insertion = (new \SqlSemantics\Binding\Write\InsertionBinder())->bind($statement, $targets[0], $scope, $values, $queries, $writes);
        }
        $modifiers = new \SqlSemantics\Binding\SelectModifiersBinder();
        [$limit, $offset] = $modifiers->pagination($statement, $scope);
        $class = match ($kind) {
            'UPDATE' => \SqlSemantics\Model\Statement\UpdateStatement::class,
            'DELETE' => \SqlSemantics\Model\Statement\DeleteStatement::class,
            default => \SqlSemantics\Model\Statement\InsertStatement::class,
        };
        return new $class($id, $input->relation ?? ($targets[0] ?? null), $scope->relations, $outputs, $where, false, $modifiers->ordering($statement, $scope, $outputs), $limit, $offset, $source, ctes: $this->context->ctes, kind: $kind, targets: $targets, assignments: $assignments, queries: $queries, rows: $values, insertion: $insertion, writes: $writes, conflicts: $conflicts);
    }

    /**
     * Separates explicitly affected tables from the complete relational input.
     *
     * @return array{?\SqlSemantics\Binding\BoundRelation, list<TableUse>}
     */
    public function input(Node $statement, string $id, string $kind): array
    {
        $joined = QueryNodes::local($statement, ['table_reference_list', 'join_table_list'])[0] ?? null;
        $binder = new FromBinder($this->context->tables, $this->context->ids, $this->context, scopeId: $id);
        $input = $joined === null ? null : $binder->bind($joined);
        $targets = $input?->scope->relations ?? $this->targets($statement, $id);
        if ($kind === 'DELETE' && $input !== null) {
            $targets = $this->deleteTargets($statement, $input->scope, $id);
        }
        $readNode = in_array($kind, ['INSERT', 'REPLACE'], true) ? null : (QueryNodes::local($statement, ['from_clause', 'using_clause', 'from'])[0] ?? null);
        $read = $readNode === null ? null : $binder->bind($readNode);
        if ($read !== null && $targets !== []) {
            $left = $input ?? new \SqlSemantics\Binding\BoundRelation($targets[0], new Scope($this->context->tables->identifiers, $targets, queries: $this->context));
            $input = $binder->join($left, $read, \SqlSemantics\Model\JoinKind::Cross, null, $statement, $this->context->ids->join());
        }
        return [$input, $targets];
    }

    /**
     * @return list<TableUse>
     */
    public function deleteTargets(Node $statement, Scope $scope, string $id): array
    {
        $list = QueryNodes::local($statement, ['table_alias_ref_list', 'table_wild_list'])[0] ?? null;
        if ($list === null) {
            return $scope->relations;
        }
        $targets = [];
        foreach (Tree::outer($list, ['table_ident_opt_wild', 'table_wild_one']) as $name) {
            $parts = array_values(array_filter($scope->identifiers->parts($name), static fn (string $part): bool => $part !== '*'));
            $matches = array_values(array_filter($scope->relations, static fn (TableUse $relation): bool => $scope->matches($relation, $parts)));
            if (count($matches) !== 1) {
                $scope->diagnostics()->report('unknown-write-target', 'A DELETE target must identify one input relation.', $name);
                $table = $this->context->tables->resolve($parts, $name);
                $targets[] = new TableUse($this->context->ids->relation(), $id, $table, null, $name);
            } else {
                $targets[] = $matches[0];
            }
        }
        return $targets;
    }

    /**
     * Keeps only written relation occurrences; unresolved unqualified names retain candidates.
     *
     * @param list<TableUse> $targets
     * @param list<\SqlSemantics\Model\Write\Assignment> $writes
     * @return list<TableUse>
     */
    public function updatedTargets(array $targets, array $writes, Scope $scope): array
    {
        $ids = [];
        foreach ($writes as $write) {
            foreach ($write->targets as $destination) {
                $column = \SqlSemantics\Model\Write\Destination::column($destination);
                if ($column->binding !== null) {
                    $ids[] = $column->binding->relationId;
                    continue;
                }
                foreach ($targets as $target) {
                    if ($scope->matches($target, array_slice($column->reference, 0, -1))) {
                        $ids[] = $target->id;
                    }
                }
            }
        }
        return array_values(array_filter($targets, static fn (TableUse $target): bool => in_array($target->id, $ids, true)));
    }

    /**
     * @param list<\SqlSemantics\Model\Write\Assignment> $writes
     * @return array<int|string, \SqlSemantics\Model\Expression> Compatibility view of scalar assignments
     */
    public function assignments(array $writes): array
    {
        $result = [];
        foreach ($writes as $write) {
            foreach ($write->targets as $target) {
                $column = \SqlSemantics\Model\Write\Destination::column($target);
                $name = $column->binding?->column->name ?? implode('.', $column->reference);
                $result[$name] = $write->value;
            }
        }
        return $result;
    }

    /**
     * @return list<TableUse>
     */
    public function targets(Node $statement, string $id): array
    {
        $tables = $this->context->tables;
        $from = in_array(StatementBinder::operation($statement), ['UPDATE', 'DELETE'], true) ? (QueryNodes::local($statement, ['table_reference_list'])[0] ?? null) : null;
        if ($from !== null) {
            return (new FromBinder($tables, $this->context->ids, $this->context, scopeId: $id))->bind($from)?->scope->relations ?? [];
        }
        $node = QueryNodes::local($statement, ['insert_target', 'relation_expr_opt_alias', 'relation_expr', 'table_ident', 'xfullname'])[0] ?? null;
        if ($node === null) {
            return [];
        }
        $name = Tree::outer($node, ['qualified_name', 'table_ident', 'nm'])[0] ?? $node;
        $declaration = $tables->resolve($tables->identifiers->parts($name), $name);
        $alias = Tree::child($node, ['ColId', 'as']);
        return [new TableUse($this->context->ids->relation(), $id, $declaration, $alias === null ? null : $tables->identifiers->parts($alias)[0], $node)];
    }
    /**
     * @param list<TableUse> $targets
     */
    public function conflictScope(Scope $scope, array $targets, Node $source, string $id): Scope
    {
        if ($targets === [] || !str_contains(strtoupper(Tree::text($source)), 'ON CONFLICT')) {
            return $scope;
        }
        $excluded = new TableUse($this->context->ids->relation(), $id, $targets[0]->declaration, 'excluded', $source);
        $parent = new Scope($scope->identifiers, [$excluded], queries: $this->context);
        return new Scope($scope->identifiers, $scope->relations, $scope->extensions, $parent, $this->context, $scope->merged);
    }

}
