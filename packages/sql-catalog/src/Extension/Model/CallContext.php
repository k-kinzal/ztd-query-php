<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Model;

use PhpParser\Node\Expr;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Extension\SinkSpec;

/**
 * A PHP call AST together with its evaluated inputs and shared object memory.
 *
 * @visibility public
 *
 * @example Inspecting a call without executing it
 *     $node = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('today_sql'));
 *     $expressions = (new \SqlCatalog\Analysis\Interpreter(new \SqlCatalog\Php\ProgramIndex(), []))->evaluatorFor();
 *     $call = new \SqlCatalog\Extension\Model\CallContext($node, [], new \SqlCatalog\Evaluation\Environment(), new \SqlCatalog\Analysis\FunctionScope('query.php'), $expressions, name: 'today_sql');
 *     $call->name // => 'today_sql'
 */
final class CallContext
{
    /**
     * @param list<Domain> $arguments Values in source argument order, including unresolved values.
     */
    public function __construct(
        public readonly Expr\CallLike $node,
        public readonly array $arguments,
        public readonly Environment $environment,
        public readonly FunctionScope $scope,
        public readonly ExpressionEvaluator $expressions,
        public readonly ?string $name = null,
        public readonly ?Domain $receiver = null,
        public readonly ?string $className = null,
        public readonly ?SinkSpec $sink = null,
    ) {
    }
}
