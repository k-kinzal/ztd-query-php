<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Session;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\QualifiedNames;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArgument;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL CALL with positional, named and variadic arguments.
 * @visibility SqlSemantics
 */
final class ProcedureCalls
{
    /**
     * Aggregate modifiers, misplaced positional arguments and repeated names are rejected as the server does before procedure lookup.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): CallProcedureStatement
    {
        $application = Tree::child($source, ['func_application']) ?? throw new UnclassifiedSql('CALL requires a procedure invocation.');
        $name = Tree::child($application, ['func_name']) ?? throw new UnclassifiedSql('CALL requires a procedure name.');
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), array_filter($application->children, static fn ($child): bool => $child instanceof Token));
        if (array_intersect($words, ['DISTINCT', '*']) !== [] || Tree::child($application, ['opt_sort_clause', 'sort_clause']) !== null) {
            throw new InvalidSql(InputViolation::ProcedureCall, $application);
        }
        $expressions = Tree::outer($application, ['func_arg_expr']);
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $arguments = [];
        foreach ($expressions as $position => $expression) {
            $parameter = Tree::child($expression, ['param_name']);
            $value = Tree::child($expression, ['a_expr']) ?? throw new UnclassifiedSql('A procedure argument requires its value.');
            try {
                $arguments[] = new ProcedureArgument(
                    (new ExpressionBinder())->bind($value, $scope),
                    $parameter === null ? null : $context->tables->identifiers->name($parameter->tokens()[0] ?? throw new UnclassifiedSql('A named argument requires its parameter name.')),
                    in_array('VARIADIC', $words, true) && $position === count($expressions) - 1,
                );
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::ProcedureCall, $expression, $error);
            }
        }
        try {
            return new CallProcedureStatement($origin, QualifiedNames::read($name, $context->tables->identifiers, InputViolation::RoutineName), $arguments);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ProcedureCall, $application, $error);
        }
    }
}
