<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Laravel;

use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * A query's immutable fields, carried through the shared object memory.
 *
 * @visibility root
 */
final class QueryState
{
    /**
     * @param array<string, Domain> $fields
     */
    public function __construct(public readonly array $fields = [])
    {
    }

    /**
     * A field, or null when it has not been set.
     */
    public function get(string $field): Domain
    {
        return $this->fields[$field] ?? Domain::literal(null);
    }

    /**
     * A new state with one field replaced.
     */
    public function with(string $field, Domain $value): self
    {
        return new self([$field => $value] + $this->fields);
    }

    /**
     * A field known as a string, or null when unresolved.
     */
    public function string(string $field): ?string
    {
        $value = $this->get($field)->soleLiteral()?->value;

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<Domain> The ordered elements of a list field.
     */
    public function items(string $field): array
    {
        return $this->get($field)->soleArray()?->positional() ?? [];
    }

    /**
     * @param list<Domain> $values New ordered elements of a field.
     */
    public function append(string $field, array $values): self
    {
        return $this->with($field, self::list(array_merge($this->items($field), $values)));
    }

    /**
     * @param list<Domain> $values An ordered array without losing value domains.
     */
    public static function list(array $values): Domain
    {
        return Domain::of(new ArrayTerm(array_map(static fn (Domain $value): ArrayEntry => new ArrayEntry(null, $value), $values)));
    }

    /**
     * Adds an explicit gap which no subsequent call may erase.
     */
    public function reject(string $reason): self
    {
        return $this->with('problem', Domain::opaque(TypeShape::of(['string']), Origin::Call, $reason));
    }

    /**
     * The snapshot of an object, or an explicitly uninitialized query.
     */
    public static function from(ObjectTerm $object): self
    {
        return $object->state === null || !$object->state->complete
            ? (new self())->reject('Laravel builder state is unavailable')
            : new self($object->state->named());
    }

    /**
     * The serializable fields kept in the shared value domain.
     */
    public function array(): ArrayTerm
    {
        $entries = [];
        foreach ($this->fields as $name => $value) {
            $entries[] = new ArrayEntry(Domain::literal($name), $value);
        }

        return new ArrayTerm($entries);
    }

    /**
     * The updated object with its identity preserved for aliases.
     */
    public function object(ObjectTerm $object): ObjectTerm
    {
        return new ObjectTerm($object->className, $object->enumCase, $object->statementId, $object->identity, $this->array());
    }
}
