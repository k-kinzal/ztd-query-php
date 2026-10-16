<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Diagnostic\Check;

/**
 * Validates array elements that PHP's array parameter type cannot enforce.
 *
 * The retained list contains only numeric input boundaries, never source text
 * or parser nodes. Constructor PHPDoc describes the intended caller contract;
 * this boundary also checks calls made without a static analyzer.
 *
 * @example Refusing retained input text
 *     (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\InputPositions())->checked(['SELECT 1']) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing objects at the array boundary
 *     (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\InputPositions())->checked([new \stdClass()]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InputPositions
{
    /**
     * Narrows untrusted array contents to ordered nonnegative positions.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $positions The PHP array received at the constructor boundary
     * @return list<int>
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the array contains any other value or key
     */
    public function checked(array $positions): array
    {
        Check::input(array_is_list($positions), 'Input positions form an ordered list.');
        $result = [];
        foreach ($positions as $position) {
            Check::input(is_int($position) && $position >= 0, 'Input positions are nonnegative integers.');
            $result[] = $position;
        }
        return $result;
    }
}
