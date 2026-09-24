<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Inspection\Session\RowWindows;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Cursor\Handler\HandlerScan;
use SqlSemantics\Model\Cursor\Handler\IndexStep;
use SqlSemantics\Model\Cursor\Handler\KeyComparison;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Cursor\Handler as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds HANDLER OPEN, CLOSE and the three READ forms: a table scan, an index walk and a key lookup.
 * @visibility SqlSemantics
 */
final class Handlers
{
    /**
     * Classifies the operation by the keyword after the handler name.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $name = Tree::child($node, ['table_ident', 'table_ident_nodb', 'ident']) ?? Tree::invalid($node, 'handler name');
        $tokens = $node->tokens();
        $action = strtoupper($tokens[count($name->tokens()) + 1]->text ?? '');
        if ($action === 'OPEN') {
            $table = TableOccurrence::resolve($node, $context, $origin->scopeId);
            return $table instanceof TableReference ? new Statement\OpenHandlerStatement($origin, $table) : Tree::invalid($node, 'opened table');
        }
        $parts = $context->tables->identifiers->parts($name);
        $declaration = $context->tables->resolve($parts, $name);
        $handler = new TableReference($context->ids->relation(), $origin->scopeId, $declaration, $context->tables->name($parts, $declaration), null, $name);
        if ($action === 'CLOSE') {
            return new Statement\CloseHandlerStatement($origin, $handler);
        }
        return self::read($origin, $node, $handler, count($name->tokens()) + 2, $context);
    }

    /**
     * Separates a scan, an index walk and a key lookup, and binds the condition against the handler's table.
     * @throws InvalidSql
     */
    public static function read(Origin $origin, Node $node, TableReference $handler, int $position, QueryContext $context): BoundStatement
    {
        $scope = new Scope($context->tables->identifiers, [$handler], queries: $context);
        $condition = Tree::child(Tree::outer($node, ['where_clause'])[0] ?? $node, ['expr']);
        $where = $condition === null ? null : (new ExpressionBinder())->bind($condition, $scope);
        $limit = RowWindows::read($node, $context);
        $tokens = $node->tokens();
        $scan = Tree::outer($node, ['handler_scan_function'])[0] ?? null;
        if ($scan !== null) {
            return new Statement\ReadHandlerStatement($origin, $handler, HandlerScan::from(strtoupper(Tree::text($scan))), $where, $limit);
        }
        $index = $context->tables->identifiers->name($tokens[$position] ?? Tree::invalid($node, 'handler index'));
        $step = strtoupper($tokens[$position + 1]->text ?? '');
        $comparison = KeyComparison::tryFrom($step);
        if ($comparison === null) {
            return new Statement\ReadHandlerIndexStatement($origin, $handler, $index, IndexStep::from($step), $where, $limit);
        }
        return new Statement\ReadHandlerKeyStatement($origin, $handler, $index, $comparison, self::key($node, new Scope($context->tables->identifiers, queries: $context)), $where, $limit);
    }

    /**
     * Binds the key values; DEFAULT names no column value in a key.
     * @return list<Expression>
     * @throws InvalidSql
     */
    public static function key(Node $node, Scope $scope): array
    {
        $values = Tree::outer($node, ['values'])[0] ?? Tree::invalid($node, 'handler key');
        $key = [];
        foreach (Tree::outer($values, ['expr_or_default']) as $value) {
            $expression = Tree::child($value, ['expr']);
            if ($expression === null) {
                throw new InvalidSql(InputViolation::DefaultContext, $value);
            }
            $key[] = (new ExpressionBinder())->bind($expression, $scope);
        }
        return $key;
    }
}
