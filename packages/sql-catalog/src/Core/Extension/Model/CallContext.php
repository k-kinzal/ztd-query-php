<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Extension\Model;

use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\ExpressionEvaluator;
use SqlCatalog\Core\Analysis\FunctionScope;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Extension\SinkSpec;

/**
 * A PHP call AST together with its evaluated inputs and shared object memory.
 *
 * @visibility public
 *
 * @example Inspecting a call without executing it
 *     $node = new \PhpParser\Node\Expr\FuncCall(new \PhpParser\Node\Name('today_sql'));
 *     $expressions = (new \SqlCatalog\Core\Analysis\Interpreter(new \SqlCatalog\Core\Php\ProgramIndex(), []))->evaluatorFor();
 *     $call = new \SqlCatalog\Core\Extension\Model\CallContext($node, [], new \SqlCatalog\Core\Evaluation\Environment(), new \SqlCatalog\Core\Analysis\FunctionScope('query.php'), $expressions, name: 'today_sql');
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
