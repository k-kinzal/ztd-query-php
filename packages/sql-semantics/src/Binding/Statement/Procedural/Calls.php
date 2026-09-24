<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\CallStatement;
use SqlSemantics\Model\Statement\Procedural\ProcedureName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds MySQL CALL with its procedure name and argument expressions.
 * @visibility SqlSemantics
 */
final class Calls
{
    /**
     * An empty or space-terminated procedure name is rejected as the server does.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): CallStatement
    {
        $name = Tree::child($node, ['sp_name']) ?? Tree::invalid($node, 'procedure name');
        $procedure = new QualifiedName(array_map(static fn (Node $part): string => $context->tables->identifiers->name($part->tokens()[0] ?? Tree::invalid($part, 'identifier')), Tree::outer($name, ['ident'])));
        try {
            ProcedureName::validate($procedure);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineName, $name, $error);
        }
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $arguments = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node && $child !== $name) {
                array_push($arguments, ...array_map(static fn (Node $argument): Expression => (new ExpressionBinder())->bind($argument, $scope), Tree::outer($child, ['expr'])));
            }
        }
        return new CallStatement($origin, $procedure, $arguments);
    }
}
