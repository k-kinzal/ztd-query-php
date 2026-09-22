<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Statement\UpdateStatement;

/**
 * Retains ordered WITH definitions independently from the visible name lookup.
 * @visibility SqlSemantics
 */
final class CteBinder
{
    /**
     * Reads the already bound declaration owned by this operation.
     */
    public function clause(Node $source, QueryContext $context): ?Query\WithClause
    {
        return CteNodes::clause($source) === null ? null : $context->withClause;
    }

    /**
     * Binds result aliases and materialization to this particular definition.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function definition(Node $node, BoundStatement $query, QueryContext $context): Query\CommonTableExpression
    {
        $nameNode = Tree::child($node, ['name', 'ident', 'withnm']);
        if ($nameNode === null || (!$query instanceof BoundQuery && !$query instanceof InsertStatement && !$query instanceof UpdateStatement && !$query instanceof DeleteStatement && !$query instanceof MergeStatement)) {
            Tree::invalid($node, 'CTE name and result query');
        }
        $name = $context->tables->identifiers->parts($nameNode)[0];
        $aliases = Tree::child($node, ['opt_name_list', 'opt_derived_column_list', 'eidlist_opt']);
        $columns = $aliases === null ? [] : array_values(array_filter($context->tables->identifiers->parts($aliases), static fn (string $part): bool => !in_array($part, ['(', ')', ','], true)));
        $width = \SqlSemantics\Model\Validation\RowShape::width($query);
        if ($width !== null && $columns !== [] && (count($columns) > $width || $query->origin->dialect !== \SqlSemantics\Dialect::PostgreSql && count($columns) !== $width)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::CteColumnCount, $node);
        }
        $policy = Tree::child($node, ['opt_materialized', 'wqas']);
        $text = $policy === null ? '' : strtoupper(Tree::text($policy));
        $mode = str_contains($text, 'NOT MATERIALIZED') ? Query\Materialization::Inline : (str_contains($text, 'MATERIALIZED') ? Query\Materialization::Materialized : Query\Materialization::Default);
        return new Query\CommonTableExpression($name, $query, $columns, $mode);
    }

    /**
     * Identifies the recursive binding policy on this WITH clause.
     */
    public static function recursive(Node $source, ?Node $clause): bool
    {
        $prefix = array_map(static fn ($token): string => strtoupper($token->text), array_slice($source->tokens(), 0, 2));
        $clausePrefix = array_map(static fn ($token): string => strtoupper($token->text), array_slice($clause?->tokens() ?? [], 0, 2));
        return $prefix === ['WITH', 'RECURSIVE'] || $clausePrefix === ['WITH', 'RECURSIVE'];
    }
}
