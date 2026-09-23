<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql;
use SqlSemantics\Model\Statement\Definition\PostgreSql;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds named MySQL deletions separately from PostgreSQL overloaded routine requests.
 * @visibility SqlSemantics
 */
final class DropRoutines
{
    /**
     * Routes only explicitly classified routine deletion families.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $tokens = $source->tokens();
        if (strtoupper($tokens[0]->text ?? '') !== 'DROP') {
            return null;
        }
        $operation = strtoupper($tokens[1]->text ?? '');
        $ifExists = strtoupper($tokens[2]->text ?? '') === 'IF';
        if ($origin->dialect === Dialect::MySql && in_array($operation, ['FUNCTION', 'PROCEDURE'], true)) {
            $names = array_map(static fn (Node $part): string => $context->tables->identifiers->name($part->tokens()[0]), Tree::outer($source, ['ident']));
            $name = new QualifiedName($names);
            return $operation === 'FUNCTION' ? new MySql\DropFunctionStatement($origin, $name, $ifExists) : new MySql\DropProcedureStatement($origin, $name, $ifExists);
        }
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        $behaviorNode = Tree::child($source, ['opt_drop_behavior']);
        $behavior = $behaviorNode === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behaviorNode)));
        if ($source->name === 'RemoveAggrStmt') {
            $targets = Collections::nonEmpty(array_map(static fn (Node $target): Routine\ZeroArgumentAggregate|Routine\OrdinaryAggregate|Routine\OrderedSetAggregate => Targets::aggregate($target, $context), Tree::outer($source, ['aggregate_with_argtypes'])));
            return new PostgreSql\DropAggregatesStatement($origin, $targets, $ifExists, $behavior);
        }
        if ($source->name !== 'RemoveFuncStmt') {
            return null;
        }
        $targets = Collections::nonEmpty(array_map(static fn (Node $target): Routine\RoutineByName|Routine\RoutineBySignature => Targets::routine($target, $context), Tree::outer($source, ['function_with_argtypes'])));
        return match ($operation) {
            'FUNCTION' => new PostgreSql\DropFunctionsStatement($origin, $targets, $ifExists, $behavior),
            'PROCEDURE' => new PostgreSql\DropProceduresStatement($origin, $targets, $ifExists, $behavior),
            'ROUTINE' => new PostgreSql\DropRoutinesStatement($origin, $targets, $ifExists, $behavior),
            default => throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Unknown routine deletion operation.'),
        };
    }
}
