<?php

declare(strict_types=1);

namespace Tests\Generator;

use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use RuntimeException;

/**
 * Evaluates public constructor defaults, including enum cases and value objects.
 */
final class Defaults
{
    /**
     * @throws RuntimeException When a default is not a supported public constant or constructor
     */
    public function value(Expr $expression): mixed
    {
        try {
            return (new ConstExprEvaluator(function (Expr $value) {
                if ($value instanceof Expr\ClassConstFetch && $value->class instanceof Name && $value->name instanceof \PhpParser\Node\Identifier) {
                    return constant($value->class->toString() . '::' . $value->name->toString());
                }
                if ($value instanceof Expr\New_ && $value->class instanceof Name) {
                    $class = $value->class->toString();
                    if (class_exists($class)) {
                        return new $class(...array_map(fn (\PhpParser\Node\Arg $argument) => $this->value($argument->value), $value->getArgs()));
                    }
                }
                throw new RuntimeException('Unsupported constructor default: ' . $value->getType());
            }))->evaluateDirectly($expression);
        } catch (\PhpParser\ConstExprEvaluationException $error) {
            throw new RuntimeException('Unsupported constructor default.', previous: $error);
        }
    }
}
