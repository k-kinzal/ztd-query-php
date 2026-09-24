<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Model;

use Closure;
use SqlCatalog\Evaluation\Domain;

/**
 * Call transformations, statement compilers and type relations registered by enabled extensions.
 *
 * @visibility public
 *
 * @example Registering a function that returns a SQL fragment
 *     $models = new \SqlCatalog\Extension\Model\ModelSet(calls: [
 *         static fn (\SqlCatalog\Extension\Model\CallContext $call): ?\SqlCatalog\Evaluation\Domain =>
 *             $call->name === 'today_sql' ? \SqlCatalog\Evaluation\Domain::literal('CURRENT_DATE') : null,
 *     ]);
 *     count($models->calls) // => 1
 */
final class ModelSet
{
    /**
     * @param list<Closure(CallContext): ?Domain> $calls Registered with the shared function-model registry; later registrations take priority.
     * @param array<string, QueryModelInterface> $queries Statement models keyed by SinkSpec::model.
     * @param list<Closure(string, string): bool> $classRelations Additional known subtype relations.
     */
    public function __construct(public readonly array $calls = [], public readonly array $queries = [], public readonly array $classRelations = [])
    {
    }

    /**
     * Combines registrations in extension order; later statement keys replace earlier ones.
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->calls, $other->calls), array_merge($this->queries, $other->queries), array_merge($this->classRelations, $other->classRelations));
    }

    /**
     * Whether an extension declares a subtype relation absent from application source.
     */
    public function matchesClass(string $class, string $expected): bool
    {
        foreach ($this->classRelations as $relation) {
            if ($relation($class, $expected)) {
                return true;
            }
        }

        return false;
    }
}
