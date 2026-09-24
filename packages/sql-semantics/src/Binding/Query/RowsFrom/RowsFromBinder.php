<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\RowsFrom;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\TableFunction\RowsFrom;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL ROWS FROM, and function calls with WITH ORDINALITY or a column definition list, as a function table; other function calls stay with the single-function binder.
 * @visibility SqlSemantics
 */
final class RowsFromBinder
{
    /**
     * Returns null when the FROM item is a single function call without WITH ORDINALITY or a column definition list, or an expanded UNNEST.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $source, Node $function, QueryContext $context, ?Scope $parent, string $scopeId): ?BoundRelation
    {
        $list = Tree::child($function, ['rowsfrom_list']);
        $ordinality = (Tree::child($function, ['opt_ordinality'])?->tokens() ?? []) !== [];
        $alias = Tree::outer($source, ['func_alias_clause'])[0] ?? null;
        $outer = $alias === null ? [] : self::definitions($alias, $context);
        $expanded = $list === null && $function->name === 'func_table' && self::expandedUnnest($function);
        if ($function->name !== 'func_table' || ($list === null && !$expanded && !$ordinality && $outer === [])) {
            return null;
        }
        $scope = $parent ?? new Scope($context->tables->identifiers, queries: $context);
        $items = $list !== null ? Tree::outer($list, ['rowsfrom_item']) : ($expanded ? self::unnests($function) : [$function]);
        if ($outer !== [] && ($ordinality || count($items) !== 1)) {
            throw new InvalidSql(InputViolation::FunctionTableColumns, $alias);
        }
        try {
            $table = new RowsFrom\RowsFromTable(Collections::nonEmpty(self::functions($items, $outer, $context, $scope)), $ordinality);
            return RowsFromRelations::relation($source, $table, $alias, $context, $scope, $parent, $scopeId);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::FunctionTableColumns, $function, $error);
        }
    }

    /**
     * Binds each invocation with its own column definition list or the one written after the alias.
     * @param list<Node> $items
     * @param list<RowsFrom\DefinedColumn> $outer
     * @return list<RowsFrom\RowsFromFunction>
     * @throws InvalidSql
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function functions(array $items, array $outer, QueryContext $context, Scope $scope): array
    {
        $functions = [];
        foreach ($items as $item) {
            $call = Tree::child($item, ['func_expr_windowless']) ?? throw new UnclassifiedSql('A function table item requires its invocation.');
            $inner = self::definitions(Tree::child($item, ['opt_col_def_list']) ?? $call, $context);
            if ($inner !== [] && $outer !== []) {
                throw new InvalidSql(InputViolation::FunctionTableColumns, $item);
            }
            $functions[] = new RowsFrom\RowsFromFunction((new ExpressionBinder())->bind($call, $scope), $inner === [] ? $outer : $inner);
        }
        return $functions;
    }

    /**
     * Reads a column definition list.
     * @return list<RowsFrom\DefinedColumn>
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function definitions(Node $source, QueryContext $context): array
    {
        $list = Tree::child($source, ['TableFuncElementList']);
        if ($list === null) {
            return [];
        }
        $columns = [];
        foreach (Tree::outer($list, ['TableFuncElement']) as $element) {
            $name = $element->tokens()[0] ?? throw new UnclassifiedSql('A defined column requires its name.');
            $type = Tree::child($element, ['Typename']) ?? throw new UnclassifiedSql('A defined column requires its type.');
            $columns[] = new RowsFrom\DefinedColumn($context->tables->identifiers->name($name), (new TypeReader(Dialect::PostgreSql))->read($type));
        }
        return $columns;
    }

    /**
     * Reports an unqualified UNNEST with several positional arrays outside ROWS FROM, which the server expands to one UNNEST per array.
     */
    public static function expandedUnnest(Node $function): bool
    {
        $application = Tree::outer($function, ['func_application'])[0] ?? null;
        $name = $application === null ? null : Tree::child($application, ['func_name']);
        if ($application === null || $name === null || strtolower(Tree::text($name)) !== 'unnest') {
            return false;
        }
        $written = \SqlSemantics\Binding\Scalar\Function\ArgumentNotations::written($application);
        foreach ($written as [$argument, $variadic]) {
            if ($variadic || Tree::child($argument, ['param_name']) !== null) {
                return false;
            }
        }
        return count($written) > 1 && Tree::child($application, ['opt_sort_clause', 'sort_clause']) === null;
    }

    /**
     * Writes `unnest(a, b)` as the ROWS FROM items `unnest(a), unnest(b)` it stands for.
     * @return list<Node>
     * @throws UnclassifiedSql
     */
    public static function unnests(Node $function): array
    {
        $application = Tree::outer($function, ['func_application'])[0] ?? throw new UnclassifiedSql('UNNEST requires its invocation.');
        $name = Tree::child($application, ['func_name']) ?? throw new UnclassifiedSql('UNNEST requires its name.');
        $tokens = array_values(array_filter($application->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token));
        $open = $tokens[0] ?? throw new UnclassifiedSql('UNNEST requires its argument list.');
        $close = $tokens[count($tokens) - 1];
        return array_map(
            static fn (array $argument): Node => new Node('rowsfrom_item', 0, [new Node('func_expr_windowless', 0, [new Node('func_application', 0, [$name, $open, new Node('func_arg_list', 0, [$argument[0]]), $close])])]),
            \SqlSemantics\Binding\Scalar\Function\ArgumentNotations::written($application),
        );
    }
}
