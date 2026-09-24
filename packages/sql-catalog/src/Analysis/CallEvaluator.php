<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Analysis\Derivation\CalleeReturns;
use SqlCatalog\Analysis\Effect\ReferenceEffects;
use SqlCatalog\Analysis\Effect\WriteEffects;
use SqlCatalog\Analysis\FunctionModel\Registry;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Extension\Model\CallContext;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\FunctionShape;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Works out what a call produces.
 *
 * A database call produces what the driver hands back: a statement handle
 * from a preparing call, the statement text itself from a call that composes
 * one. A call into the analyzed source produces what the callee returns for
 * these arguments. Recording statements is not done here; it is done at each
 * database call, from the call itself.
 *
 * @visibility root
 */
final class CallEvaluator
{
    private ProgramIndex $index;

    private SinkMatcher $sinks;

    private Registry $functions;

    private ExternalInput $external;

    private NodeText $text;

    private ?CalleeReturns $returns;

    private ?ReferenceEffects $references = null;

    /**
     * Wires the evaluator to everything a call may need.
     */
    public function __construct(
        ProgramIndex $index,
        SinkMatcher $sinks,
        Registry $functions,
        ExternalInput $external,
        NodeText $text,
        ?CalleeReturns $returns = null,
    ) {
        $this->index = $index;
        $this->sinks = $sinks;
        $this->functions = $functions;
        $this->external = $external;
        $this->text = $text;
        $this->returns = $returns;
    }

    /**
     * The value a call produces.
     */
    public function evaluate(
        Expr\CallLike $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        if ($node->isFirstClassCallable()) {
            return Domain::of(new ObjectTerm('Closure'));
        }
        $receiver = $node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall ? $expressions->evaluate($node->var, $environment, $scope) : null;
        $arguments = $this->arguments($node, $environment, $scope, $expressions);

        if ($node instanceof Expr\New_) {
            $modelled = $this->functions->evaluateCall(new CallContext($node, $arguments, $environment, $scope, $expressions, className: $node->class instanceof Node\Name ? $node->class->toString() : null));
            if ($modelled !== null) {
                return $modelled;
            }
            $this->applyEffects($node, $environment);
            $this->invalidateEscapes($node, $environment);
            return $this->evaluateInstantiation($node, $scope);
        }
        if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
            return $this->evaluateMethod($node, $arguments, $environment, $scope, $expressions, $receiver);
        }
        if ($node instanceof Expr\StaticCall) {
            return $this->evaluateStatic($node, $arguments, $scope, $expressions, $environment);
        }
        if ($node instanceof Expr\FuncCall) {
            return $this->evaluateFunction($node, $arguments, $scope, $expressions, $environment);
        }

        return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
    }

    /**
     * Opens arguments whose callees may write through references.
     */
    public function applyEffects(Expr\CallLike $node, Environment $environment): void
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = $this->functionName($node->name);
            if ($this->sinks->matchFunction($name) !== null) {
                return;
            }
            if ($this->index->findFunction($name) === null && $this->functions->supports($name)) {
                $count = $node->getArgs()[3] ?? null;
                if ($this->functions->normalize($name) === 'str_replace' && $count !== null) {
                    (new WriteEffects())->apply((new Derivation\ModifiedNames())->targets($count->value), $environment);
                }
                return;
            }
        }
        if ($node instanceof Expr\MethodCall && $node->name instanceof Node\Identifier
            && $node->var instanceof Expr\Variable && is_string($node->var->name)
            && $this->sinks->matchMethod($environment->read($node->var->name), $node->name->toString()) !== null) {
            return;
        }
        $effects = new WriteEffects();
        $written = $effects->own($node, $this->index);
        $this->references ??= new ReferenceEffects();
        $effects->apply($written + $this->references->affected($node, $written), $environment);
    }

    /**
     * The object an instantiation produces, which is how a driver gets its type.
     */
    public function evaluateInstantiation(Expr\New_ $node, FunctionScope $scope): Domain
    {
        if (!$node->class instanceof Node\Name) {
            return Domain::opaque(TypeShape::of(['object']), Origin::Call, 'new');
        }
        $className = $node->class->toString();
        if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
            $className = $scope->className ?? $className;
        }

        return Domain::of(new ObjectTerm($className));
    }

    /**
     * The evaluated arguments of a call, in the order they are written.
     *
     * @return list<Domain>
     */
    public function arguments(
        Expr\CallLike $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): array {
        $arguments = [];
        foreach ($node->getArgs() as $argument) {
            $arguments[] = $expressions->evaluate($argument->value, $environment, $scope);
        }

        return $arguments;
    }

    /**
     * The value of a method call, which may be a database call.
     *
     * @param list<Domain> $arguments
     */
    public function evaluateMethod(
        Expr\MethodCall|Expr\NullsafeMethodCall $node,
        array $arguments,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
        ?Domain $receiver = null,
    ): Domain {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        if ($name === null) {
            $this->applyEffects($node, $environment);
            $this->invalidateEscapes($node, $environment);
            $environment->objects()->invalidate($receiver ?? $expressions->evaluate($node->var, $environment, $scope));
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }

        $receiver = $environment->refresh($receiver ?? $expressions->evaluate($node->var, $environment, $scope));
        $sink = $this->sinks->matchMethod($receiver, $name);
        $modelled = $this->functions->evaluateCall(new CallContext($node, $arguments, $environment, $scope, $expressions, $name, $receiver, sink: $sink));
        if ($modelled !== null) {
            return $modelled;
        }
        $this->applyEffects($node, $environment);
        if ($sink !== null) {
            return $this->applySink($sink, $node, $arguments, $scope);
        }
        if (!$this->isOwnReceiver($node) && $this->sinks->models($receiver)) {
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }

        $this->invalidateEscapes($node, $environment);
        $className = $receiver->type()->soleClassName();
        $method = $this->index->findMethod($className, $name);
        if ($method?->node?->getStmts() === null) {
            $dispatched = $this->dispatch($className, $name, $arguments, $scope, $expressions);
            if ($dispatched !== null) {
                return $dispatched;
            }
        }

        return $method === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node))
            : $this->follow($method, $arguments, $scope, $expressions);
    }

    /**
     * Whether the call is written on the object the body it is written in belongs to.
     *
     * A class an extension models is not followed into from outside, but its
     * own source is still read the way any other source is, so a call it makes
     * on itself is followed like any other.
     */
    public function isOwnReceiver(Expr\MethodCall|Expr\NullsafeMethodCall $node): bool
    {
        return $node->var instanceof Expr\Variable && $node->var->name === 'this';
    }

    /**
     * The value a call resolves to across the implementations the source declares.
     *
     * @param list<Domain> $arguments
     */
    public function dispatch(
        ?string $className,
        string $method,
        array $arguments,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): ?Domain {
        $result = null;
        foreach ($this->index->implementationsOf($className, $method) as $implementation) {
            $value = $this->follow($implementation, $arguments, $scope, $expressions);
            $result = $result === null ? $value : $result->union($value);
        }

        return $result;
    }

    /**
     * The value of a static call, which may be a database call.
     *
     * @param list<Domain> $arguments
     */
    public function evaluateStatic(
        Expr\StaticCall $node,
        array $arguments,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
        ?Environment $environment = null,
    ): Domain {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        $className = $node->class instanceof Node\Name ? $node->class->toString() : null;
        if ($name === null || $className === null) {
            if ($environment !== null) {
                $this->applyEffects($node, $environment);
                $this->invalidateEscapes($node, $environment);
            }
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }
        if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
            $className = $scope->className ?? $className;
        }

        $sink = $this->sinks->matchStatic($className, $name);
        $environment ??= new Environment();
        $modelled = $this->functions->evaluateCall(new CallContext($node, $arguments, $environment, $scope, $expressions, $name, className: $className, sink: $sink));
        if ($modelled !== null) {
            return $modelled;
        }
        $this->applyEffects($node, $environment);
        if ($sink !== null) {
            return $this->applySink($sink, $node, $arguments, $scope);
        }

        $this->invalidateEscapes($node, $environment);
        $method = $this->index->findMethod($className, $name);

        return $method === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node))
            : $this->follow($method, $arguments, $scope, $expressions);
    }

    /**
     * The value of a function call, which may be a database call or a string builder.
     *
     * @param list<Domain> $arguments
     */
    public function evaluateFunction(
        Expr\FuncCall $node,
        array $arguments,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
        ?Environment $environment = null,
    ): Domain {
        if (!$node->name instanceof Node\Name) {
            if ($environment !== null) {
                $this->applyEffects($node, $environment);
                $this->invalidateEscapes($node, $environment);
            }
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }
        $name = $this->functionName($node->name);

        $sink = $this->sinks->matchFunction($name);
        $environment ??= new Environment();
        $modelled = $this->functions->evaluateCall(new CallContext($node, $arguments, $environment, $scope, $expressions, $name, sink: $sink));
        if ($modelled !== null) {
            return $modelled;
        }
        $this->applyEffects($node, $environment);
        if ($sink !== null) {
            return $this->applySink($sink, $node, $arguments, $scope);
        }
        $modeled = $this->functions->evaluate($name, $arguments);
        if ($modeled !== null) {
            return $modeled;
        }
        if ($this->external->isFunction($name)) {
            return Domain::opaque(TypeShape::unknown(), Origin::External, $name . '()');
        }

        $this->invalidateEscapes($node, $environment);

        $function = $this->index->findFunction($name);

        return $function === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node))
            : $this->follow($function, $arguments, $scope, $expressions);
    }

    /**
     * Unknown calls may mutate objects passed directly or captured by callbacks.
     */
    public function invalidateEscapes(Expr\CallLike $node, Environment $environment): void
    {
        $arguments = array_map(static fn (Node\Arg $argument): Expr => $argument->value, array_values($node->getArgs()));
        foreach ((new Derivation\FreeNames())->of($arguments) as $name => $_) {
            $environment->objects()->invalidate($environment->read($name));
        }
    }

    /**
     * Resolves an unqualified call locally before PHP's global fallback.
     */
    public function functionName(Node\Name $name): string
    {
        $local = $name->getAttribute('namespacedName');
        if ($local instanceof Node\Name
            && ($this->functions->supports($local->toString()) || $this->index->findFunction($local->toString()) !== null)
        ) {
            return $local->toString();
        }

        return $name->toString();
    }

    /**
     * What a database call hands back.
     *
     * A preparing call hands back a handle naming the call it came from, which
     * is how a later `execute()` finds the statement it binds to. A composing
     * call hands back the statement it was given.
     *
     * @param list<Domain> $arguments
     */
    public function applySink(SinkSpec $sink, Expr\CallLike $node, array $arguments, FunctionScope $scope): Domain
    {
        return match ($sink->role) {
            SinkRole::Compose => $arguments[$sink->sqlParameter ?? 0] ?? Domain::unknown(),
            SinkRole::Prepare => Domain::of(new ObjectTerm(
                $sink->handleType ?? 'PDOStatement',
                null,
                $this->siteKeyOf($node, $scope, $sink->id),
            )),
            SinkRole::Query, SinkRole::Modelled => Domain::opaque(TypeShape::unknown(), Origin::Call, $sink->id),
            SinkRole::Execute, SinkRole::Bind => Domain::opaque(TypeShape::of(['bool']), Origin::Call, $sink->id),
        };
    }

    /**
     * What a call into the analyzed source returns for these arguments.
     *
     * @param list<Domain> $arguments
     */
    public function follow(
        MethodShape|FunctionShape $callee,
        array $arguments,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
    ): Domain {
        if ($this->returns === null) {
            $name = $callee instanceof MethodShape ? $callee->qualifiedName() : $callee->name;

            return Domain::opaque($callee->returnType, Origin::Call, $name . '()');
        }

        return $this->returns->valueOf($callee, $arguments, $scope, $expressions);
    }

    /**
     * What tells one call apart from every other, including one written on the same line.
     *
     * The reported site names a line, because that is what a reader jumps to.
     * Grouping the readings of a call needs more than that, since two queries
     * on one line are two calls and must not be folded into each other.
     */
    public function siteKeyOf(Expr\CallLike $node, FunctionScope $scope, string $sinkId): string
    {
        return $this->callKeyOf($node, $scope) . ':' . $sinkId;
    }

    /**
     * What tells one call apart from every other, by where it is written.
     */
    public function callKeyOf(Expr\CallLike $node, FunctionScope $scope): string
    {
        return $scope->file . ':' . $node->getStartFilePos();
    }
}
