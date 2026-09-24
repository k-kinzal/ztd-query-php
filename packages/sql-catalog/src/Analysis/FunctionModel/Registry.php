<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\FunctionModel;

use SqlCatalog\Analysis\BuiltinCallModel;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Extension\Model\CallContext;

/**
 * Registered interpretations of PHP function calls, from evaluated arguments to values.
 *
 * @visibility public
 * @example Registering a constant result
 *     $models = \SqlCatalog\Analysis\FunctionModel\Registry::withBuiltins();
 *     $models->register('App\table', static fn (array $arguments) => \SqlCatalog\Evaluation\Domain::literal('users'));
 *     $models->evaluate('App\table', [])?->soleLiteral()?->value // => 'users'
 *     $models->evaluate('strtoupper', [\SqlCatalog\Evaluation\Domain::literal('users')])?->soleLiteral()?->value // => 'USERS'
 */
final class Registry
{
    /**
     * @var array<string, list<callable(list<Domain>): ?Domain>>
     */
    private array $models = [];

    /**
     * @var list<callable(CallContext): ?Domain>
     */
    private array $calls = [];

    /**
     * The standard models, which may be extended or overridden through register().
     */
    public static function withBuiltins(): self
    {
        $registry = new self();
        (new BuiltinCallModel())->register($registry);

        return $registry;
    }

    /**
     * Adds a model tried before earlier models of the same function.
     * Return null to defer to earlier models, then ordinary source analysis.
     *
     * @param callable(list<Domain>): ?Domain $model The interpretation of the arguments in source order
     */
    public function register(string $name, callable $model): void
    {
        $this->models[$this->normalize($name)][] = $model;
    }

    /**
     * Whether at least one model is registered for this fully qualified name.
     */
    public function supports(string $name): bool
    {
        return isset($this->models[$this->normalize($name)]);
    }

    /**
     * The first model that accepts the arguments, or null to use source analysis.
     *
     * @param list<Domain> $arguments
     */
    public function evaluate(string $name, array $arguments): ?Domain
    {
        foreach (array_reverse($this->models[$this->normalize($name)] ?? []) as $model) {
            $value = $model($arguments);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Adds a context-aware model for PHP calls, including methods and constructors.
     *
     * @param callable(CallContext): ?Domain $model The AST, evaluated values and shared object state.
     */
    public function registerCall(callable $model): void
    {
        $this->calls[] = $model;
    }

    /**
     * Tries newer context-aware models first, preserving ordinary function models as a fallback.
     */
    public function evaluateCall(CallContext $context): ?Domain
    {
        foreach (array_reverse($this->calls) as $model) {
            $value = $model($context);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * A case-insensitive function name retaining its namespace.
     */
    public function normalize(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }
}
