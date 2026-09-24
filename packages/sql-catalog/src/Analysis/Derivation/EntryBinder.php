<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\Slice\Arrival;
use SqlCatalog\Analysis\Derivation\Slice\Pending;
use SqlCatalog\Analysis\EvaluationBudget;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Php\DeclaredGlobals;
use SqlCatalog\Php\TypeReader;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Binds what a path still needs when it reaches the start of its body.
 *
 * A parameter is bound to what each call of the body passes for it, which is
 * worked out at the call the same way the statement was worked out at the
 * database call. A property of `$this` is bound to each value the class can
 * leave it holding. A name read at the top of a file is bound to what an
 * extension says it holds. Whatever cannot be bound this way is left open,
 * and says why: nothing calls the body, the budget ran out, or the name is
 * simply not defined anywhere the analysis can see.
 *
 * @visibility root
 */
final class EntryBinder
{
    private Callers $callers;

    private ExpressionEvaluator $expressions;

    private EvaluationBudget $budget;

    private DeclaredGlobals $globals;

    private ?PropertyWrites $properties;

    private TypeReader $types;

    /**
     * Wires the binder to what it looks for callers and property values with.
     */
    public function __construct(
        Callers $callers,
        ExpressionEvaluator $expressions,
        EvaluationBudget $budget,
        ?DeclaredGlobals $globals = null,
        ?PropertyWrites $properties = null,
    ) {
        $this->callers = $callers;
        $this->expressions = $expressions;
        $this->budget = $budget;
        $this->globals = $globals ?? new DeclaredGlobals();
        $this->properties = $properties;
        $this->types = new TypeReader();
    }

    /**
     * The ways in, with those beyond what the budget can run one at a time joined into one.
     *
     * Every way in runs the whole path, so a long path with many ways in costs
     * their product. The ways the budget cannot pay for are joined value by
     * value into a single way in: every value is still there, and the result
     * says that its values were paired without knowing they go together.
     *
     * @param list<Binding> $bindings
     * @return list<Binding>
     */
    public function affordable(array $bindings, int $limit): array
    {
        if (count($bindings) <= $limit) {
            return $bindings;
        }
        $kept = array_slice($bindings, 0, max(0, $limit - 1));
        $joined = null;
        $through = [];
        $truncated = false;
        foreach (array_slice($bindings, max(0, $limit - 1)) as $binding) {
            $joined = $joined === null ? $binding->environment : $joined->join($binding->environment);
            $through = $binding->through;
            $truncated = $truncated || $binding->truncated;
        }
        $kept[] = new Binding($joined ?? new Environment(), $through, $truncated, true);

        return $kept;
    }

    /**
     * The ways into the body a path arrived at the start of, each binding what the path still needs.
     *
     * @return list<Binding>
     */
    public function bindings(Arrival $arrival, int $depth, Deriver $deriver): array
    {
        $bindings = $this->parameterBindings($arrival, $depth, $deriver);
        $className = $arrival->body instanceof Stmt\ClassMethod && !$arrival->body->isStatic() ? $deriver->classOf($arrival->body) : null;
        if ($className === null || $this->properties === null || $arrival->path->exhausted) {
            return $bindings;
        }
        $varying = 0;
        foreach ($arrival->path->needs as $needed => $_) {
            if (str_starts_with($needed, FreeNames::THIS . '->')) {
                $values = $this->properties->valuesOf($className, substr($needed, strlen(FreeNames::THIS) + 2), $depth, $deriver);
                $varying += count($values) > 1 ? 1 : 0;
                $bindings = $this->withProperty($bindings, $needed, $values, $varying > 1);
            }
        }

        return $bindings;
    }

    /**
     * The ways in, one for each value a property can hold on each of them.
     *
     * Properties are worked out one at a time, so once more than one of them
     * can hold several values, the pairings are not known to occur together and
     * the ways in say so.
     *
     * @param list<Binding> $bindings
     * @param list<Domain> $values
     * @return list<Binding>
     */
    public function withProperty(array $bindings, string $name, array $values, bool $combined = false): array
    {
        if ($values === []) {
            return $bindings;
        }
        $split = [];
        foreach ($bindings as $binding) {
            foreach ($values as $value) {
                $environment = $binding->environment->copy();
                $environment->write($name, $value);
                $split[] = new Binding($environment, $binding->through, $binding->truncated, $binding->combined || $combined);
            }
        }
        if (count($split) <= Deriver::MAX_SOLUTIONS) {
            return $split;
        }

        return array_map(
            static fn (Binding $binding): Binding => new Binding($binding->environment, $binding->through, true, $binding->combined),
            array_slice($split, 0, Deriver::MAX_SOLUTIONS),
        );
    }

    /**
     * What a parameter of a body can be, for every way into the body.
     *
     * @return list<Domain>
     */
    public function parameterValues(FunctionLike $body, string $name, int $depth, Deriver $deriver): array
    {
        $values = [];
        foreach ($this->parameterBindings(new Arrival($body, Pending::needing([$name => true])), $depth, $deriver) as $binding) {
            $values[] = $binding->environment->read($name);
        }

        return $values;
    }

    /**
     * The value of an expression that reads nothing but constants, such as a property default.
     */
    public function evaluateConstant(Expr $expression, ?string $className): Domain
    {
        return $this->expressions->evaluate($expression, new Environment(), new FunctionScope('', FunctionScope::MAIN, $className));
    }

    /**
     * The ways into the body, each binding the parameters and other names the path still needs.
     *
     * A local with no reaching definition starts unset. Its null type makes
     * `isset` false, while its unresolved origin still flags an unguarded read.
     * Parameters, declared globals and exhausted paths keep their open values.
     *
     * @return list<Binding>
     */
    public function parameterBindings(Arrival $arrival, int $depth, Deriver $deriver): array
    {
        $body = $arrival->body;
        $name = $body === null ? FunctionScope::MAIN : $deriver->nameOf($body);
        $needs = $arrival->path->needs;
        if ($arrival->path->exhausted) {
            return [new Binding($this->leaveOpen($needs, Origin::Budget), [$name])];
        }
        if ($body === null) {
            return [new Binding($this->fileScope($needs), [$name])];
        }

        $parameters = $this->parametersOf($body, $needs);
        $outside = new Environment();
        foreach (array_diff_key($needs, $parameters) as $needed => $_) {
            if ($needed !== FreeNames::THIS && !str_starts_with($needed, FreeNames::THIS . '->')) {
                $outside->write($needed, Domain::opaque(TypeShape::of(['null']), Origin::Unresolved, '$' . $needed));
            }
        }
        if ($parameters === []) {
            return [new Binding($outside, [$name])];
        }
        if ($depth >= $this->budget->maxDepth || $this->budget->isExhausted()) {
            return [new Binding($this->withParameters($outside, $parameters, Origin::Budget), [$name])];
        }
        $callers = $this->callers->of($body, $depth, $deriver);
        if ($callers->calls === []) {
            $origin = $callers->partial ? $this->openOrigin() : Origin::Parameter;

            return [new Binding($this->withParameters($outside, $parameters, $origin), [$name], $callers->partial)];
        }

        $bindings = $this->fromCallers($callers, $parameters, $outside, $name, $depth, $deriver);
        if ($bindings !== []) {
            return $bindings;
        }
        return [new Binding($this->withParameters($outside, $parameters, $this->openOrigin()), [$name])];
    }

    /**
     * One way in for every way each call can pass the arguments the path needs.
     *
     * When not every caller could be asked — there are more than the limit, the
     * budget ran out partway, or a call might reach the body without that being
     * certain — every way in found is marked as cut short, not only the ones
     * found after the search stopped: the set as a whole is what is incomplete.
     *
     * @param array<string, array{int, Node\Param}> $parameters
     * @return list<Binding>
     */
    public function fromCallers(CallerSet $callers, array $parameters, Environment $outside, string $name, int $depth, Deriver $deriver): array
    {
        $truncated = $callers->partial || count($callers->calls) > Deriver::MAX_CALLERS;
        $found = [];
        foreach (array_slice($callers->calls, 0, Deriver::MAX_CALLERS) as $call) {
            if ($this->budget->isExhausted()) {
                $truncated = true;
                break;
            }
            $arguments = $this->argumentsFor($call, $parameters);
            $goals = array_values(array_filter($arguments, static fn (?Expr $argument): bool => $argument !== null));
            foreach ($deriver->solve($call, $goals, $depth + 1) as $solution) {
                $environment = $outside->copy();
                $position = 0;
                foreach ($arguments as $parameter => $argument) {
                    $environment->write($parameter, $argument === null
                        ? $this->defaultOf($parameters[$parameter][1])
                        : $solution->values[$position++]);
                }
                $found[] = [$environment, $solution];
            }
        }

        return array_map(
            static fn (array $one): Binding => new Binding(
                $one[0],
                array_merge($one[1]->through, [$name]),
                $truncated || $one[1]->truncated,
                $one[1]->combined,
            ),
            $found,
        );
    }

    /**
     * The argument a call passes for each needed parameter, or null where it passes none.
     *
     * @param array<string, array{int, Node\Param}> $parameters
     * @return array<string, Expr|null>
     */
    public function argumentsFor(Expr\CallLike $call, array $parameters): array
    {
        $arguments = [];
        $written = $call->isFirstClassCallable() ? [] : $call->getArgs();
        foreach ($parameters as $name => [$position, $parameter]) {
            $found = null;
            foreach ($written as $index => $argument) {
                if ($argument->name !== null ? $argument->name->toString() === $name : $index === $position) {
                    $found = $argument->unpack ? null : $argument->value;
                }
            }
            $arguments[$name] = $parameter->variadic ? null : $found;
        }

        return $arguments;
    }

    /**
     * What a parameter holds when a call does not pass it.
     */
    public function defaultOf(Node\Param $parameter): Domain
    {
        $name = $parameter->var instanceof Expr\Variable && is_string($parameter->var->name) ? $parameter->var->name : '';
        if ($parameter->default !== null && !$parameter->variadic) {
            return $this->expressions->evaluate($parameter->default, new Environment(), new FunctionScope(''));
        }

        return Domain::opaque($this->types->read($parameter->type), Origin::Parameter, ($parameter->variadic ? '...$' : '$') . $name);
    }

    /**
     * Why a parameter no caller could be asked about is left open: the budget, or there being no caller.
     */
    public function openOrigin(): Origin
    {
        return $this->budget->isExhausted() ? Origin::Budget : Origin::Parameter;
    }

    /**
     * The parameters of a body among the names a path needs, with their positions.
     *
     * @param array<string, true> $needs
     * @return array<string, array{int, Node\Param}>
     */
    public function parametersOf(FunctionLike $body, array $needs): array
    {
        $parameters = [];
        foreach ($body->getParams() as $position => $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)
                && isset($needs[$parameter->var->name])) {
                $parameters[$parameter->var->name] = [$position, $parameter];
            }
        }

        return $parameters;
    }

    /**
     * The environment with every needed parameter left open, for the given reason.
     *
     * @param array<string, array{int, Node\Param}> $parameters
     */
    public function withParameters(Environment $environment, array $parameters, Origin $origin): Environment
    {
        $bound = $environment->copy();
        foreach ($parameters as $name => [, $parameter]) {
            $bound->write($name, Domain::opaque($this->types->read($parameter->type), $origin, '$' . $name));
        }

        return $bound;
    }

    /**
     * Every needed name left open, for the given reason.
     *
     * @param array<string, true> $needs
     */
    public function leaveOpen(array $needs, Origin $origin): Environment
    {
        $environment = new Environment();
        foreach ($needs as $name => $_) {
            $environment->write($name, Domain::opaque(TypeShape::unknown(), $origin, '$' . $name));
        }

        return $environment;
    }

    /**
     * What the names code at the top of a file reads without defining are known to hold.
     *
     * Such code runs in the global scope, so a name it reads was set by
     * whatever ran before it. An extension may say what that is; otherwise it
     * is left open.
     *
     * @param array<string, true> $needs
     */
    public function fileScope(array $needs): Environment
    {
        $environment = new Environment();
        foreach ($needs as $name => $_) {
            $className = $this->globals->declared($name);
            $environment->write($name, $className !== null
                ? Domain::of(new ObjectTerm($className))
                : Domain::opaque(TypeShape::unknown(), Origin::Unresolved, '$' . $name));
        }

        return $environment;
    }
}
