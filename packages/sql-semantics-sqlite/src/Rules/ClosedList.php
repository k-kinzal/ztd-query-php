<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;

/**
 * Narrows a constructor argument to an ordered list whose items are of one of several admitted classes.
 *
 * PHP does not check array element types, so every list a constructor accepts
 * passes through here whatever its PHPDoc promises; a list of one class goes
 * through the core check instead.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ClosedList
{
    /**
     * Answers the items as a list, rejecting any item that is of none of the classes, a non-list array, or too few items.
     *
     * @template T of object
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $items
     * @param list<class-string<T>> $classes
     *
     * @return ($minimum is positive-int ? non-empty-list<T> : list<T>)
     *
     * @throws InvalidConstruction When the argument is not a list of the admitted classes, or is shorter than the minimum
     */
    public function of(array $items, array $classes, string $message, int $minimum = 0): array
    {
        $list = [];
        foreach ($items as $item) {
            $accepted = null;
            foreach ($classes as $class) {
                if ($item instanceof $class) {
                    $accepted = $item;
                }
            }
            if ($accepted === null) {
                throw new InvalidConstruction($message);
            }
            $list[] = $accepted;
        }
        if (!array_is_list($items) || count($list) < $minimum) {
            throw new InvalidConstruction($message);
        }

        return $list;
    }
}
