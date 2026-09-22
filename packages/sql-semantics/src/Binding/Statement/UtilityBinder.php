<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;

/**
 * Preserves utility operations and binds their embedded query dependencies.
 *
 * @visibility SqlSemantics
 */
final class UtilityBinder
{
    /**
     * Resolves embedded queries in the caller's semantic environment.
     */
    public function __construct(public readonly QueryContext $context)
    {
    }

    /**
     * Binds DDL, transaction, maintenance, and administrative grammar statements.
     * @throws UnclassifiedSql
     */
    public function bind(Node $source, Node $statement, string $kind): BoundStatement
    {
        $id = $this->context->ids->scope();
        $children = Tree::significant($statement);
        while (count($children) === 1 && $children[0] instanceof Node) {
            $statement = $children[0];
            $children = Tree::significant($statement);
        }
        $origin = new \SqlSemantics\Model\Statement\Origin($id, $source, $this->context->tables->identifiers->dialect);
        $operation = Session\ExecutionBinder::bind($origin, $statement, $this->context);
        if ($operation !== null) {
            return $operation;
        }
        if ($kind === 'PRAGMA') {
            return \SqlSemantics\Binding\Configuration\PragmaBinder::bind($origin, $statement, new \SqlSemantics\Binding\Scope($this->context->tables->identifiers, queries: $this->context));
        }
        $declaration = Definition\CreateBinder::bind($origin, $statement, $this->context);
        if ($declaration !== null) {
            return $declaration;
        }
        $configuration = \SqlSemantics\Binding\Configuration\ConfigurationBinder::bind($origin, $statement, $kind, new \SqlSemantics\Binding\Scope($this->context->tables->identifiers, queries: $this->context));
        if ($configuration !== null) {
            return $configuration;
        }
        throw new UnclassifiedSql('Unclassified statement ' . $statement->name . ': ' . $source->toString());
    }
    /**
     * Stops at each nested command boundary, including utility wrappers around queries.
     *
     * @return list<Node>
     */
    public function commands(Node $node): array
    {
        if (str_ends_with($node->name, 'Stmt') || str_ends_with($node->name, '_stmt') || in_array($node->name, ['select', 'query_expression', 'statement'], true)) {
            return [$node];
        }
        $result = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node && Tree::hasTokens($child)) {
                array_push($result, ...$this->commands($child));
            }
        }
        return $result;
    }
}
