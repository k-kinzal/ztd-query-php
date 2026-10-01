<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use OutOfBoundsException;
use SqlSemantics\Statement\Relation\Scope;

/**
 * Ordered result fields whose persistent updates preserve column ownership.
 * @visibility public
 * @example Constructing an empty projection for a language that permits it
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('public')), complete: false);
 *     (new \SqlSemantics\Statement\Projection\Fields(new \SqlSemantics\Statement\Relation\Scope($catalog)))->items // => []
 */
final class Fields
{
    /**
     * @var list<Field>
     */
    public readonly array $items;

    /**
     * Asserts scope identity for every nested column expression.
     */
    public function __construct(public readonly Scope $scope, Field ...$fields)
    {
        $this->items = array_values($fields);
        foreach ($fields as $field) {
            foreach ($field->expression->references() as $reference) {
                assert($reference->scope === $scope, 'Every projected column must belong to this scope.');
            }
        }
    }

    /**
     * Adds an owned field without modifying the original projection or declaration.
     */
    public function addField(Field $field): self
    {
        return new self($this->scope, ...[...$this->items, $field]);
    }

    /**
     * Returns a uniquely named result position; duplicate labels stay ambiguous.
     * @throws OutOfBoundsException When the name is absent or ambiguous
     */
    public function field(string $name): Field
    {
        $matches = array_values(array_filter($this->items, fn (Field $field): bool => $this->scope->catalog->columnNames->equal($name, $field->name->value)));
        if (count($matches) !== 1) {
            throw new OutOfBoundsException($matches === [] ? 'No result field named ' . $name : 'Ambiguous result field: ' . $name);
        }
        return $matches[0];
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
