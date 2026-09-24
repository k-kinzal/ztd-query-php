<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scalar\VariableBinder;
use SqlSemantics\Binding\Statement\Retrieval\IntoPlacement;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\SelectIntoStatement;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Statement\Retrieval\RetrievedQuery;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Nullability;

/**
 * Binds SELECT ... INTO with local variable targets; a list of only user variables keeps the ordinary SELECT INTO form.
 * @visibility SqlSemantics
 */
final class ProgramRetrievals
{
    /**
     * Returns null for statements without a local variable INTO target.
     * @throws InvalidSql
     */
    public static function bind(Node $statement, ProgramFrame $frame): ?SelectIntoStatement
    {
        $clauses = IntoPlacement::clauses($statement);
        $targets = $clauses === [] ? [] : Tree::outer($clauses[0], ['select_var_ident']);
        if (array_filter($targets, static fn (Node $target): bool => ($target->tokens()[0]->text ?? '') !== '@') === []) {
            return null;
        }
        if (count($clauses) > 1 || !IntoPlacement::last($statement, $clauses[0])) {
            throw new InvalidSql(InputViolation::SelectInto, $clauses[count($clauses) - 1]);
        }
        $query = $frame->context->bind($statement);
        $variables = array_map(static fn (Node $target): LocalVariableReference|VariableReference|UnresolvedVariableReference => self::target($target, $frame), $targets);
        $width = RetrievedQuery::width($query);
        if ($width !== null && $width !== count($variables)) {
            throw new InvalidSql(InputViolation::SelectInto, $clauses[0]);
        }
        return new SelectIntoStatement($query, $variables);
    }

    /**
     * Resolves a user variable written with @ or a declared local variable written without it.
     * @throws InvalidSql
     */
    public static function target(Node $target, ProgramFrame $frame): LocalVariableReference|VariableReference|UnresolvedVariableReference
    {
        $tokens = $target->tokens();
        if (($tokens[0]->text ?? '') === '@') {
            return (new VariableBinder())->bind($target, $frame->scope());
        }
        $variable = $frame->names()->variable(MySqlNames::read($tokens[0] ?? throw new InvalidSql(InputViolation::ProgramObject, $target), $frame->context->tables->identifiers)) ?? throw new InvalidSql(InputViolation::ProgramObject, $target);
        return new LocalVariableReference(new ExpressionFacts($variable->domain->type, Nullability::MaybeNull), $target, $variable);
    }
}
