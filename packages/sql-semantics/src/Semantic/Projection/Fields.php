<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Projection;

use LogicException;
use OutOfBoundsException;
use SqlSemantics\Semantic\Expression\Operands;
use SqlSemantics\Semantic\Scope;

/**
 * Ordered result positions with scope-checked persistent updates.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo, foo FROM bar');
 *     count($statement->fields()->items) // => 2
 *
 * @visibility public
 */
final class Fields
{
    /**
     * @var non-empty-list<Field|Star>
     */
    public readonly array $items;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Scope $scope, Field|Star $first, Field|Star ...$rest)
    {
        $this->items = [$first, ...array_values($rest)];
        foreach ($this->items as $field) {
            if ($field instanceof Field) {
                Operands::check($scope, $field->expression);
            } else {
                assert($field->scope === $scope, 'A star must belong to its projection scope.');
            }
        }
    }

    /**
     * Returns a new collection with a field whose scope agrees with this collection.
     */
    public function addField(Field $field): self
    {
        return new self($this->scope, $this->items[0], ...[...array_slice($this->items, 1), $field]);
    }

    /**
     * Returns the uniquely named result field; duplicate names remain ambiguous.
     * @throws OutOfBoundsException
     */
    public function field(string $name): Field
    {
        $matches = array_values(array_filter($this->outputs(), fn (Field $field): bool => $field->name !== null && $this->scope->dialect->platform()->names()->equal($field->name->value, $name)));
        if (count($matches) !== 1) {
            throw new OutOfBoundsException($matches === [] ? 'No result field named ' . $name : 'Ambiguous result field: ' . $name);
        }
        return $matches[0];
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return implode(', ', array_map(static fn (Field|Star $field): string => $field->toString(), $this->items));
    }

    /**
     * @return list<Field>
     * @throws LogicException
     */
    public function outputs(): array
    {
        $outputs = [];
        foreach ($this->items as $item) {
            if ($item instanceof Star && $item->fields === null) {
                throw new LogicException('A catalog is required to expand this projection.');
            }
            array_push($outputs, ...$item instanceof Field ? [$item] : $item->fields);
        }
        return $outputs;
    }
}
