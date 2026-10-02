<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use ReflectionReference;

/**
 * Multiple output positions have the requested name, in projection order.
 * @visibility public
 * @example Ambiguity retains distinct positions even for the same expression
 *     $field = new \SqlSemantics\Statement\Projection\Field(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Identifier\Name('n'));
 *     $matches = (new \SqlSemantics\Statement\Projection\AmbiguousFields([0 => $field, 1 => $field]))->matches;
 *     $matches[0] === $field && $matches[1] === $field // => true
 */
final class AmbiguousFields
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var array<int, Field>
     */
    public readonly array $matches;

    /**
     * Preserves ordered output indexes; no first-match choice is made.
     * @template T
     * @param array<array-key, T> $matches
     */
    public function __construct(array $matches)
    {
        \SqlSemantics\Statement\Validation\Check::input(count($matches) >= 2, 'Ambiguity needs at least two output positions.');
        $previous = -1;
        $verified = [];
        foreach ($matches as $position => $field) {
            \SqlSemantics\Statement\Validation\Check::input(is_int($position) && $position > $previous && $field instanceof Field, 'Ambiguous outputs retain increasing nonnegative positions and actual fields.');
            \SqlSemantics\Statement\Validation\Check::input(ReflectionReference::fromArrayElement($matches, $position) === null, 'An output lookup cannot retain mutable array references.');
            $previous = $position;
            $verified[$position] = $field;
        }
        $this->matches = $verified;
    }
}
