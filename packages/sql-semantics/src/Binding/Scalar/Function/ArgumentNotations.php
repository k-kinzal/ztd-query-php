<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Function;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Function\Argument\ArgumentOrder;
use SqlSemantics\Model\Scalar\Function\Argument\NamedArgument;
use SqlSemantics\Model\Scalar\Function\Argument\VariadicArgument;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Attaches PostgreSQL named and VARIADIC notation to the bound arguments of one invocation.
 * @visibility SqlSemantics
 */
final class ArgumentNotations
{
    /**
     * Wraps each argument written as `name => value`, `name := value` or `VARIADIC value` in its notation.
     *
     * @param list<Expression> $arguments Bound argument values in written order
     * @return list<Expression>
     * @throws InvalidSql
     */
    public static function apply(Node $source, array $arguments, Scope $scope): array
    {
        $application = $source->name === 'func_application' ? $source : Tree::child($source, ['func_application']);
        if ($application === null || $scope->identifiers->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            return $arguments;
        }
        $written = self::written($application);
        if (count($written) !== count($arguments) || array_filter($written, static fn (array $argument): bool => $argument[1] || Tree::child($argument[0], ['param_name']) !== null) === []) {
            return $arguments;
        }
        $notated = [];
        try {
            foreach ($written as $position => [$node, $variadic]) {
                $name = Tree::child($node, ['param_name']);
                $value = $arguments[$position];
                if ($name !== null) {
                    $value = new NamedArgument($value->facts, $node, $scope->identifiers->name($name->tokens()[0] ?? throw new InvalidSql(InputViolation::FunctionArgumentNotation, $node)), $value);
                }
                $notated[] = $variadic ? new VariadicArgument($value->facts, $node, $value) : $value;
            }
            ArgumentOrder::validate($notated);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::FunctionArgumentNotation, $application, $error);
        }
        return $notated;
    }

    /**
     * Returns the written arguments of an application, each with whether VARIADIC precedes it.
     *
     * @return list<array{Node, bool}>
     */
    public static function written(Node $list): array
    {
        $written = [];
        $variadic = false;
        foreach ($list->children as $child) {
            if ($child instanceof Token) {
                $variadic = strtoupper($child->text) === 'VARIADIC';
            } elseif ($child->name === 'func_arg_list') {
                array_push($written, ...self::written($child));
            } elseif ($child->name === 'func_arg_expr') {
                $written[] = [$child, $variadic];
                $variadic = false;
            }
        }
        return $written;
    }
}
