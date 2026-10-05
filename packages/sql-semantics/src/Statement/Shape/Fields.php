<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Shape;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Snapshot;

/**
 * The ordered, read-only output fields of a query whose shape is complete.
 *
 * Order and duplicate names are kept. The collection offers listing,
 * iteration, counting, positional lookup and name lookup, and nothing that
 * adds, removes, reorders or replaces a field.
 *
 * @visibility public
 * @implements IteratorAggregate<int, Field>
 * @example Looking fields up by position and by name
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS a, 2 AS a, 3 AS b');
 *     $fields = $operation->fields();
 *     [count($fields), $fields->at(2)->name?->value, $fields->lookup('a') instanceof \SqlSemantics\Statement\Shape\AmbiguousFields] // => [3, 'b', true]
 */
final class Fields implements Countable, IteratorAggregate
{
    use Snapshot;

    /**
     * @var list<Field> The fields in output order
     */
    public readonly array $items;

    /**
     * @param list<Field> $items The fields in output order
     * @param Comparison $names How output names are compared
     */
    public function __construct(array $items, public readonly Comparison $names)
    {
        $this->items = Check::listOf($items, Field::class, 'Fields are an ordered list of fields.');
        foreach ($this->items as $position => $field) {
            Check::input($field->position === $position, 'Each field holds its own output position.');
        }
    }

    /**
     * Counts the output positions.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Iterates the fields in output order.
     *
     * @return ArrayIterator<int, Field>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Answers the field at a zero-based output position.
     */
    public function at(int $position): Field
    {
        Check::input(isset($this->items[$position]), 'No field exists at the requested position.');

        return $this->items[$position];
    }

    /**
     * Finds the fields a name denotes, distinguishing one, none and several.
     *
     * When a field's name depends on missing inputs, no lookup is decided: the
     * known matches are candidates of a dependent lookup.
     */
    public function lookup(string $name): UniqueField|AbsentField|AmbiguousFields|DependentField
    {
        $matches = [];
        $missing = [];
        foreach ($this->items as $field) {
            if ($field->name !== null && $this->names->equal($field->name->value, $name)) {
                $matches[] = $field;
            }
            array_push($missing, ...$field->slot->unnamed);
        }
        if ($missing !== []) {
            return new DependentField($name, $matches, $missing);
        }
        if ($matches === []) {
            return new AbsentField($name);
        }

        return count($matches) === 1 ? new UniqueField($matches[0]) : new AmbiguousFields($name, $matches);
    }
}
