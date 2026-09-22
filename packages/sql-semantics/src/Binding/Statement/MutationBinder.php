<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use LogicException;
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
    public function __construct(public readonly QueryContext $context, public readonly ?Scope $parent = null)
    {
    }

    /**
     * Binds a mutation while keeping its result separate from its input query.
     * @throws LogicException
     */
    public function bind(Node $source, Node $statement): BoundStatement
    {
        $id = $this->context->ids->scope();
        $kind = StatementBinder::operation($statement);
        [$input, $targets] = $this->input($statement, $id, $kind);
        $scope = $input->scope ?? new Scope($this->context->tables->identifiers, $targets, parent: $this->parent, queries: $this->context);
        $scope = $this->conflictScope($scope, $targets, $statement, $id);
        $writes = (new \SqlSemantics\Binding\Write\AssignmentBinder())->bind($statement, $scope, new Scope($scope->identifiers, $targets, queries: $this->context));
        if ($kind === 'UPDATE') {
            $targets = $this->updatedTargets($targets, $writes, $scope);
        }
        $conflicts = (new \SqlSemantics\Binding\Write\ConflictBinder())->bind($statement, $scope);
        $whereNode = in_array($kind, ['INSERT', 'REPLACE'], true) ? null : (QueryNodes::local($statement, ['where_clause', 'opt_where_clause', 'where_or_current_clause', 'where_opt', 'where_opt_ret'])[0] ?? null);
        if ($whereNode !== null && strtoupper($whereNode->tokens()[0]->text ?? '') !== 'WHERE') {
            $whereNode = null;
        }
        $where = (new \SqlSemantics\Binding\Write\ConflictBinder())->predicate($whereNode, $scope);
        $returning = QueryNodes::local($statement, ['returning_clause', 'returning', 'where_opt_ret', 'upsert'])[0] ?? null;
        $outputs = $returning === null || !str_contains(strtoupper(Tree::text($returning)), 'RETURNING') ? [] : (new ProjectionBinder())->bind($returning, $scope);
        $queries = [];
        $directRows = [];
        $queryNames = ['SelectStmt', 'query_expression', 'select', 'select_init', 'select_paren', 'insert_query_expression', 'create_select'];
        foreach (Tree::outer($statement, [...$queryNames, 'with_clause', 'with', 'wqlist', 'a_expr', 'expr', 'expr_or_default', 'values_list', 'opt_on_conflict', 'upsert', 'insert_update_list']) as $query) {
            if (in_array($query->name, $queryNames, true)) {
                $body = QueryNodes::body($query);
                if (strtoupper($body->tokens()[0]->text ?? '') === 'VALUES' && QueryNodes::local($query, ['sort_clause', 'order_clause', 'orderby_opt', 'limit_clause', 'limit_opt', 'with_clause']) === []) {
                    $directRows = \SqlSemantics\Binding\Write\WriteInputs::rows($body, $scope);
                    continue;
                }
                $queries[] = $this->context->bind($query, $this->parent);
            }
        }
        $values = $queries === [] ? ($directRows !== [] ? $directRows : \SqlSemantics\Binding\Write\WriteInputs::rows($statement, $scope)) : ($queries[0] instanceof \SqlSemantics\Model\Statement\ValuesStatement ? $queries[0]->rows : []);
        $insertion = null;
        if (in_array($kind, ['INSERT', 'REPLACE'], true)) {
            if ($targets === []) {
                Tree::invalid($statement, 'INSERT destination');
            }
            $insertion = (new \SqlSemantics\Binding\Write\InsertionBinder())->bind($statement, $targets[0], $scope, $values, $queries, $writes);
        }
        $modifiers = new \SqlSemantics\Binding\SelectModifiersBinder();
        [$limit, $offset] = $modifiers->pagination($statement, $scope);
        $origin = new \SqlSemantics\Model\Statement\Origin($id, $source, $this->context->tables->identifiers->dialect);
        $order = $modifiers->ordering($statement, $scope, null);
        return match ($kind) {
            'UPDATE' => MutationForms::update($origin, $statement, $input, $targets, $writes, $where, $outputs, $this->context, $order, $limit),
            'DELETE' => MutationForms::delete($origin, $statement, $input, $targets, $where, $outputs, $this->context, $order, $limit),
            'INSERT', 'REPLACE' => (new InsertBinder())->statement($origin, $statement, $insertion, $values, $queries, $writes, \SqlSemantics\Model\Write\InsertMode::from($kind), $outputs, $conflicts, (new \SqlSemantics\Binding\Query\CteBinder())->clause($source, $this->context)),
            default => throw new LogicException('Unclassified mutation: ' . $kind),
        };
    }

    /**
     * Separates explicitly affected tables from the complete relational input.
     *
     * @return array{?\SqlSemantics\Binding\BoundRelation, list<TableUse>}
     */
    public function input(Node $statement, string $id, string $kind): array
    {
        $joined = QueryNodes::local($statement, ['table_reference_list', 'join_table_list'])[0] ?? null;
        $binder = new FromBinder($this->context->tables, $this->context->ids, $this->context, $this->parent, scopeId: $id);
        $input = $joined === null ? null : $binder->bind($joined);
        $targets = $input?->scope->relations ?? $this->targets($statement, $id);
        if ($kind === 'DELETE' && $input !== null) {
            $targets = $this->deleteTargets($statement, $input->scope, $id);
        }
        $readNode = in_array($kind, ['INSERT', 'REPLACE'], true) ? null : (QueryNodes::local($statement, ['from_clause', 'using_clause', 'from'])[0] ?? null);
        $read = $readNode === null ? null : $binder->bind($readNode);
        if ($read !== null && $targets !== []) {
            $left = $input ?? new \SqlSemantics\Binding\BoundRelation($targets[0], new Scope($this->context->tables->identifiers, $targets, parent: $this->parent, queries: $this->context));
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
                $targets[] = new \SqlSemantics\Model\Relation\TableReference($this->context->ids->relation(), $id, $table, $this->context->tables->name($parts, $table), null, $name);
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
            foreach ($write->destinations() as $destination) {
                $column = $destination->column();
                if ($column->columnBinding() !== null) {
                    $ids[] = $column->columnBinding()->relationId;
                    continue;
                }
                foreach ($targets as $target) {
                    if ($scope->matches($target, array_slice($column->referenceParts(), 0, -1))) {
                        $ids[] = $target->id;
                    }
                }
            }
        }
        return array_values(array_filter($targets, static fn (TableUse $target): bool => in_array($target->id, $ids, true)));
    }

    /**
     * @return list<TableUse>
     */
    public function targets(Node $statement, string $id): array
    {
        $tables = $this->context->tables;
        $from = in_array(StatementBinder::operation($statement), ['UPDATE', 'DELETE'], true) ? (QueryNodes::local($statement, ['table_reference_list'])[0] ?? null) : null;
        if ($from !== null) {
            return (new FromBinder($tables, $this->context->ids, $this->context, $this->parent, scopeId: $id))->bind($from)?->scope->relations ?? [];
        }
        $node = QueryNodes::local($statement, ['insert_target', 'relation_expr_opt_alias', 'relation_expr', 'table_ident', 'xfullname', 'trnm'])[0] ?? null;
        if ($node === null) {
            return [];
        }
        $name = Tree::outer($node, ['qualified_name', 'table_ident'])[0] ?? $node;
        if ($node->name === 'xfullname') {
            $tokens = $node->tokens();
            $aliasPosition = array_search('AS', array_map(static fn ($token): string => strtoupper($token->text), $tokens), true);
            $name = new Node('relation_name', 0, $aliasPosition === false ? $tokens : array_slice($tokens, 0, $aliasPosition));
        }
        $declaration = $tables->resolve($tables->identifiers->parts($name), $name);
        $alias = Tree::child($node, ['ColId', 'as']);
        return [new \SqlSemantics\Model\Relation\TableReference($this->context->ids->relation(), $id, $declaration, $tables->name($tables->identifiers->parts($name), $declaration), $alias === null ? null : $tables->identifiers->parts($alias)[0], $node)];
    }
    /**
     * @param list<TableUse> $targets
     */
    public function conflictScope(Scope $scope, array $targets, Node $source, string $id): Scope
    {
        if ($targets === [] || !str_contains(strtoupper(Tree::text($source)), 'ON CONFLICT')) {
            return $scope;
        }
        $excluded = new \SqlSemantics\Model\Relation\ProposedRow($this->context->ids->relation(), $id, $targets[0]->declaration, 'excluded', $source, $targets[0]);
        $parent = new Scope($scope->identifiers, [$excluded], queries: $this->context);
        return new Scope($scope->identifiers, $scope->relations, $scope->extensions, $parent, $this->context, $scope->merged);
    }

}
