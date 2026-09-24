<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\SemanticException;

/**
 * Reads ordering and pagination without confusing output aliases with input names.
 *
 * @visibility SqlSemantics
 */
final class SelectModifiersBinder
{
    /**
     * @param list<OutputColumn>|null $outputs
     * @return list<Ordering>
     */
    public function ordering(Node $statement, Scope $scope, ?array $outputs): array
    {
        $windowed = $this->windowed($statement);
        $nodes = array_values(array_filter(QueryNodes::local($statement, ['sortby', 'order_expr']), static fn (Node $node): bool => !isset($windowed[spl_object_id($node)])));
        $sqlite = array_values(array_filter(QueryNodes::local($statement, ['orderby_opt']), static fn (Node $node): bool => !isset($windowed[spl_object_id($node)])))[0] ?? null;
        if ($sqlite !== null) {
            $list = Tree::child($sqlite, ['sortlist']);
            $nodes = $list === null ? [] : Query\OrderingNodes::read($list);
        }
        if ($nodes === []) {
            $legacy = QueryNodes::local($statement, ['order_clause'])[0] ?? null;
            $list = $legacy === null ? null : Tree::child($legacy, ['order_list']);
            $nodes = $list === null ? [] : Query\OrderingNodes::read($list);
        }
        $result = [];
        foreach ($nodes as $node) {
            $expr = Tree::child(Tree::child($node, ['order_ident']) ?? $node, ['a_expr', 'expr']);
            if ($expr === null) {
                Tree::invalid($node, 'ordering');
            }
            $direction = Tree::child($node, ['opt_asc_desc', 'opt_ordering_direction', 'ordering_direction', 'order_dir', 'sortorder']);
            $nulls = Tree::child($node, ['opt_nulls_order', 'nulls']);
            $expression = $this->sortExpression($expr, $scope, $outputs);
            $result[] = new Ordering($expression, $direction !== null && strtoupper(Tree::text($direction)) === 'DESC', $nulls === null ? null : str_contains(strtoupper(Tree::text($nulls)), 'FIRST'));
        }

        return $result;
    }

    /**
     * Identifies ordering nodes that belong to named window definitions rather than to the query result.
     * @return array<int, true>
     */
    public function windowed(Node $statement): array
    {
        $result = [];
        foreach (QueryNodes::local($statement, ['opt_window_clause', 'window_clause']) as $clause) {
            foreach (['sortby', 'order_expr', 'orderby_opt'] as $name) {
                foreach ($clause->find($name) as $node) {
                    $result[spl_object_id($node)] = true;
                }
            }
        }
        return $result;
    }

    /**
     * @param list<OutputColumn>|null $outputs
     * @throws SemanticException
     */
    public function sortExpression(Node $node, Scope $scope, ?array $outputs): Expression|\SqlSemantics\Model\Query\Ordering\OutputPosition|\SqlSemantics\Model\Query\Ordering\OutputAlias|\SqlSemantics\Model\Query\Ordering\UnresolvedOutputPosition
    {
        if ($outputs === null) {
            return (new ExpressionBinder())->bind($node, $scope);
        }
        $tokens = $node->tokens();
        if (count($tokens) === 1 && !in_array($tokens[0]->name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true)) {
            $text = $tokens[0]->text;
            if (ctype_digit($text)) {
                $ordinal = (int) $text - 1;
                if (!isset($outputs[$ordinal])) {
                    if ($ordinal >= 0 && array_filter($outputs, static fn (OutputColumn $output): bool => $output->expression instanceof \SqlSemantics\Model\Scalar\Reference\Wildcard) !== []) {
                        return new \SqlSemantics\Model\Query\Ordering\UnresolvedOutputPosition(new \SqlSemantics\Type\Identity\Numeric\NumericParameter($text));
                    }
                    throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::OutputPosition, $node);
                }
                return new \SqlSemantics\Model\Query\Ordering\OutputPosition($outputs[$ordinal]);
            }
            $name = $scope->identifiers->name($tokens[0]);
            $matches = array_values(array_filter($outputs, static fn (OutputColumn $output): bool => $output->name !== null && $scope->identifiers->equal($output->name, $name)));
            if (count($matches) > 1) {
                $scope->diagnostics()->report('ambiguous-output', 'ORDER BY alias is ambiguous.', $node);
                return (new ExpressionBinder())->bind($node, $scope);
            }
            if ($matches !== []) {
                return new \SqlSemantics\Model\Query\Ordering\OutputAlias($matches[0]);
            }
        }

        return (new ExpressionBinder())->bind($node, $scope);
    }

    /**
     * FETCH FIRST ROW without a count asks for one row; any other clause without an expression supplies no limit.
     * @return list<Expression>
     */
    public function implicitRow(?Node $limit, Scope $scope): array
    {
        return $limit !== null && str_starts_with(strtoupper(Tree::text($limit)), 'FETCH') ? [Expression::literal(1, $scope->identifiers->dialect)] : [];
    }

    /**
     * Reads the row limit and offset; FETCH FIRST ROW without a count limits the result to one row.
     * @return array{?Expression, ?Expression}
     */
    public function pagination(Node $statement, Scope $scope): array
    {
        $limit = QueryNodes::local($statement, ['limit_clause', 'limit_opt', 'opt_simple_limit'])[0] ?? null;
        $offset = QueryNodes::local($statement, ['offset_clause'])[0] ?? null;
        $expressions = $limit === null ? [] : Tree::outer($limit, ['a_expr', 'expr', 'limit_option', 'select_fetch_first_value']);
        $bound = $expressions === [] ? $this->implicitRow($limit, $scope) : array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), $expressions);
        if (count($bound) > 2) {
            Tree::invalid($statement, 'pagination');
        }
        if ($limit !== null && count($bound) === 2 && str_contains(Tree::text($limit), ',')) {
            return [$bound[1], $bound[0]];
        }
        if ($offset !== null) {
            $node = Tree::outer($offset, ['a_expr', 'expr', 'select_fetch_first_value'])[0] ?? null;
            if ($node === null) {
                Tree::invalid($offset, 'offset');
            }
            return [$bound[0] ?? null, (new ExpressionBinder())->bind($node, $scope)];
        }
        if ($limit !== null && Tree::hasTokens($limit) && $bound === [] && strtoupper(Tree::text($limit)) !== 'LIMIT ALL') {
            Tree::invalid($limit, 'limit');
        }

        return [$bound[0] ?? null, $bound[1] ?? null];
    }
}
