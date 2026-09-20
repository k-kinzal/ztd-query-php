<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Evaluation\CallResults;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\PathSet;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\FunctionShape;
use SqlCatalog\Php\MethodShape;
use SqlCatalog\Php\NodeText;
use SqlCatalog\Php\ProgramIndex;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Evaluates a call: records it when it reaches the database, follows it otherwise.
 *
 * @visibility root
 */
final class CallEvaluator
{
    private ProgramIndex $index;

    private SinkMatcher $sinks;

    private StatementRecorder $recorder;

    private ValueBinder $binder;

    /**
     * @var list<string>
     */
    private array $through = [];

    private CallResults $followed;

    private BuiltinCallModel $builtins;

    private ExternalInput $external;

    private EvaluationBudget $budget;

    private NodeText $text;

    /**
     * Wires the evaluator to everything a call may need.
     */
    public function __construct(
        ProgramIndex $index,
        SinkMatcher $sinks,
        StatementRecorder $recorder,
        BuiltinCallModel $builtins,
        ExternalInput $external,
        EvaluationBudget $budget,
        NodeText $text,
    ) {
        $this->index = $index;
        $this->sinks = $sinks;
        $this->recorder = $recorder;
        $this->binder = new ValueBinder($recorder);
        $this->followed = new CallResults();
        $this->builtins = $builtins;
        $this->external = $external;
        $this->budget = $budget;
        $this->text = $text;
    }

    /**
     * The value a call produces, after recording whatever it sends to the database.
     */
    public function evaluate(
        Expr\CallLike $node,
        Environment $environment,
        FunctionScope $scope,
        ExpressionEvaluator $expressions,
        BodyWalker $bodies,
    ): Domain {
        $this->recorder->markVisited($this->callKeyOf($node, $scope));
        $arguments = $this->arguments($node, $environment, $scope, $expressions);

        if ($node instanceof Expr\New_) {
            return $this->evaluateInstantiation($node, $scope);
        }
        if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
            return $this->evaluateMethod($node, $arguments, $environment, $scope, $expressions, $bodies);
        }
        if ($node instanceof Expr\StaticCall) {
            return $this->evaluateStatic($node, $arguments, $scope, $bodies);
        }
        if ($node instanceof Expr\FuncCall) {
            return $this->evaluateFunction($node, $arguments, $scope, $bodies);
        }

        return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
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
        BodyWalker $bodies,
    ): Domain {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        if ($name === null) {
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }

        $receiver = $expressions->evaluate($node->var, $environment, $scope);
        $sink = $this->sinks->matchMethod($receiver, $name);
        if ($sink !== null) {
            return $this->applySink($sink, $node, $arguments, $receiver, $scope);
        }
        if ($receiver->type()->classNames() !== []) {
            $this->recorder->markExplained($this->callKeyOf($node, $scope));
        }
        if (!$this->isOwnReceiver($node) && $this->sinks->models($receiver)) {
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }

        $className = $receiver->type()->soleClassName();
        $method = $this->index->findMethod($className, $name);
        if ($method?->node?->getStmts() === null) {
            $dispatched = $this->dispatch($className, $name, $arguments, $scope, $bodies);
            if ($dispatched !== null) {
                return $dispatched;
            }
        }

        return $method === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node))
            : $this->follow($method, $arguments, $scope, $bodies);
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
        BodyWalker $bodies,
    ): ?Domain {
        $result = null;
        foreach ($this->index->implementationsOf($className, $method) as $implementation) {
            $value = $this->follow($implementation, $arguments, $scope, $bodies);
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
        BodyWalker $bodies,
    ): Domain {
        $name = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
        $className = $node->class instanceof Node\Name ? $node->class->toString() : null;
        if ($name === null || $className === null) {
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }
        if (in_array(strtolower($className), ['self', 'static', 'parent'], true)) {
            $className = $scope->className ?? $className;
        }

        $sink = $this->sinks->matchStatic($className, $name);
        if ($sink !== null) {
            return $this->applySink($sink, $node, $arguments, Domain::of(new ObjectTerm($className)), $scope);
        }
        $this->recorder->markExplained($this->callKeyOf($node, $scope));

        $method = $this->index->findMethod($className, $name);

        return $method === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node))
            : $this->follow($method, $arguments, $scope, $bodies);
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
        BodyWalker $bodies,
    ): Domain {
        if (!$node->name instanceof Node\Name) {
            return Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node));
        }
        $name = $node->name->toString();

        $sink = $this->sinks->matchFunction($name);
        if ($sink !== null) {
            return $this->applySink($sink, $node, $arguments, Domain::unknown(), $scope);
        }
        $this->recorder->markExplained($this->callKeyOf($node, $scope));
        if ($this->external->isFunction($name)) {
            return Domain::opaque(TypeShape::unknown(), Origin::External, $name . '()');
        }
        if ($this->builtins->supports($name)) {
            return $this->builtins->evaluate($name, $arguments);
        }

        $function = $this->index->findFunction($name);

        return $function === null
            ? Domain::opaque(TypeShape::unknown(), Origin::Call, $this->text->render($node))
            : $this->follow($function, $arguments, $scope, $bodies);
    }

    /**
     * Records what a matched database call sends, and returns what it produces.
     *
     * @param list<Domain> $arguments
     */
    public function applySink(
        SinkSpec $sink,
        Expr\CallLike $node,
        array $arguments,
        Domain $receiver,
        FunctionScope $scope,
    ): Domain {
        $site = $this->siteOf($node, $scope, $sink->id);
        $siteKey = $this->siteKeyOf($node, $scope, $sink->id);
        $this->through = $scope->stack;
        $this->recorder->markExplained($this->callKeyOf($node, $scope));

        if ($sink->role === SinkRole::Compose) {
            return $arguments[$sink->sqlParameter ?? 0] ?? Domain::unknown();
        }
        if ($sink->role === SinkRole::Query) {
            $records = $this->recordStatements($sink, $arguments, $site, $siteKey);
            $this->binder->bindValues($records, $sink, $arguments);

            return Domain::opaque(TypeShape::unknown(), Origin::Call, $sink->id);
        }
        if ($sink->role === SinkRole::Prepare) {
            return $this->applyPrepare($sink, $arguments, $site, $siteKey);
        }

        $this->binder->bindValues($this->binder->openRecords($receiver), $sink, $arguments);

        return Domain::opaque(TypeShape::of(['bool']), Origin::Call, $sink->id);
    }

    /**
     * Records the statement a preparing call carries and hands back its handle.
     *
     * @param list<Domain> $arguments
     */
    public function applyPrepare(SinkSpec $sink, array $arguments, CallSite $site, string $siteKey): Domain
    {
        $this->recorder->filePrepared($siteKey, $this->recordStatements($sink, $arguments, $site, $siteKey));

        return Domain::of(new ObjectTerm($sink->handleType ?? 'PDOStatement', null, $siteKey));
    }

    /**
     * The statements a call carries, one for each alternative the text resolved to.
     *
     * @param list<Domain> $arguments
     * @return list<QueryRecord>
     */
    public function recordStatements(SinkSpec $sink, array $arguments, CallSite $site, string $siteKey): array
    {
        $sql = $sink->sqlParameter === null ? null : ($arguments[$sink->sqlParameter] ?? null);
        if ($sql === null) {
            return [];
        }

        $records = [];
        foreach ($sql->patterns() as $pattern) {
            $records[] = $this->recorder->record($site, $siteKey, $pattern, $sink->kind, $sql->combined, $this->through);
        }

        return $records;
    }

    /**
     * The value a call into analyzed code produces, following it when the budget allows.
     *
     * Following a call is what resolves a statement that a repository assembles
     * in one method and issues in another. The budget bounds how deep that goes.
     *
     * @param list<Domain> $arguments
     */
    public function follow(
        MethodShape|FunctionShape $callee,
        array $arguments,
        FunctionScope $scope,
        BodyWalker $bodies,
    ): Domain {
        $name = $callee instanceof MethodShape ? $callee->qualifiedName() : $callee->name;
        $body = $callee->node?->getStmts();
        if ($scope->depth() >= $this->budget->maxDepth || $scope->isFollowing($name)) {
            return Domain::opaque($callee->returnType, Origin::Budget, $name . '()');
        }
        if ($body === null) {
            return Domain::opaque($callee->returnType, Origin::Call, $name . '()');
        }

        $memo = $this->followed->keyFor($name, $arguments);
        $remembered = $this->followed->recall($memo);
        if ($remembered !== null) {
            return $remembered;
        }

        $environment = new Environment();
        foreach ($callee->parameters as $position => $parameter) {
            $environment->write(
                $parameter->name,
                $arguments[$position] ?? Domain::opaque($parameter->type, Origin::Parameter, '$' . $parameter->name),
            );
        }
        $className = $callee instanceof MethodShape ? $callee->className : $scope->className;

        $result = $bodies->walk($body, PathSet::of($environment), $scope->enter($name, $className, $callee->file));
        $this->followed->remember($memo, $result);

        return $result;
    }

    /**
     * Where a call is written.
     */
    public function siteOf(Expr\CallLike $node, FunctionScope $scope, string $sinkId): CallSite
    {
        return new CallSite($scope->file, $node->getStartLine(), $scope->function, $sinkId);
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
