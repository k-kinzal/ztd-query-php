<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use OutOfBoundsException;
use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Relation\Scope;

/**
 * Ordered read-only result fields with scope ownership and explicit lookup outcomes.
 * @implements IteratorAggregate<int, Field>
 * @visibility public
 * @example Constructing an empty projection for a language that permits it
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('public')), complete: false);
 *     (new \SqlSemantics\Statement\Projection\Fields(new \SqlSemantics\Statement\Relation\Scope($catalog)))->items // => []
 */
final class Fields implements Countable, IteratorAggregate
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<Field>
     */
    public readonly array $items;

    /**
     * Checks scope identity for every nested column expression.
     */
    public function __construct(public readonly Scope $scope, Field ...$fields)
    {
        $this->items = array_values($fields);
        foreach ($fields as $field) {
            \SqlSemantics\Statement\Validation\Check::input((new Ownership())->accepts($field->expression, $scope), 'Every projected column must belong to this scope.');
        }
    }

    /**
     * Returns a uniquely named result position; duplicate labels stay ambiguous.
     * @throws OutOfBoundsException When the name is absent or ambiguous
     */
    public function field(string $name): Field
    {
        $lookup = $this->lookupField($name);
        if (!$lookup instanceof UniqueField) {
            throw new OutOfBoundsException($lookup instanceof AbsentField ? 'No result field named ' . $name : 'Ambiguous result field: ' . $name);
        }
        return $lookup->field;
    }

    /**
     * Distinguishes a unique position, absence, and all competing output positions.
     */
    public function lookupField(string $name): UniqueField|AbsentField|AmbiguousFields
    {
        $matches = array_filter($this->items, fn (Field $field): bool => $this->scope->catalog->columnNames->equal($name, $field->name->value));
        $position = array_key_first($matches);
        if ($position === null) {
            return AbsentField::Value;
        }
        return count($matches) === 1 ? new UniqueField($position, $matches[$position]) : new AmbiguousFields($matches);
    }

    /**
     * Returns an exact zero-based output position, retaining duplicates.
     * @throws OutOfBoundsException
     */
    public function at(int $position): Field
    {
        return $this->items[$position] ?? throw new OutOfBoundsException('No output position ' . $position);
    }

    /**
     * Counts output positions, including duplicate labels and expressions.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Iterates over a copy of the container; every element remains immutable.
     * @return ArrayIterator<int, Field>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Returns every written alias matching a name, retaining order and original field identities.
     * @return list<Field>
     */
    public function matchingAliases(string $name): array
    {
        return array_values(array_filter($this->items, fn (Field $field): bool => $field->alias !== null && $this->scope->catalog->columnNames->equal($name, $field->alias->value)));
    }

    /**
     * Reconstructs the ordered SELECT list from its expressions and aliases.
     */
    public function toString(): string
    {
        return implode(', ', array_map(static fn (Field $field): string => $field->toString(), $this->items));
    }
}
