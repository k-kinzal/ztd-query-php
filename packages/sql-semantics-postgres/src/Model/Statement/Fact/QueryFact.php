<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Fact;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\DependentField;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\FieldLookup;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Fields;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * What an operation derived for the output of one query.
 *
 * The projection lists the output fields in order. A star that missing
 * declarations prevent from expanding stays a typed request and leaves the
 * shape open; no field is invented for it.
 *
 * @visibility SqlSemantics
 */
final class QueryFact
{
    use Snapshot;

    /**
     * @var list<Field|OpenStar> The output fields and unexpandable stars in order
     */
    public readonly array $projection;

    /**
     * @var RowShape The output positions; open when a star could not be expanded
     */
    public readonly RowShape $shape;

    /**
     * @param array<array-key, object> $projection The output fields and unexpandable stars in order, as a list of Field and OpenStar
     * @param Comparison $names How output names are compared
     *
     * @throws InvalidConstruction When the projection holds anything but fields and open stars
     */
    public function __construct(array $projection, public readonly Comparison $names)
    {
        $slots = [];
        $missing = [];
        $items = [];
        foreach ($projection as $item) {
            if ($item instanceof Field) {
                $slots[] = $item->slot;
            } elseif ($item instanceof OpenStar) {
                array_push($missing, ...$item->missing);
            } else {
                throw new InvalidConstruction('A projection holds fields and open stars.');
            }
            $items[] = $item;
        }
        Check::input(array_is_list($projection), 'A projection is an ordered list.');
        $this->projection = $items;
        $this->shape = new RowShape($slots, $missing);
    }

    /**
     * Answers the complete field list, or null while a star cannot be expanded.
     */
    public function fields(): ?Fields
    {
        $fields = [];
        foreach ($this->projection as $item) {
            if ($item instanceof OpenStar) {
                return null;
            }
            $fields[] = $item;
        }

        return new Fields($fields, $this->names);
    }

    /**
     * Looks an output field up by name without choosing among several or guessing in an open shape.
     */
    public function lookup(string $name): FieldLookup
    {
        $fields = $this->fields();
        if ($fields !== null) {
            return $fields->lookup($name);
        }
        $candidates = [];
        foreach ($this->projection as $item) {
            if ($item instanceof Field && $item->name !== null && $this->names->equal($item->name->value, $name)) {
                $candidates[] = $item;
            }
        }

        return new DependentField($name, $candidates, $this->shape->missing);
    }
}
