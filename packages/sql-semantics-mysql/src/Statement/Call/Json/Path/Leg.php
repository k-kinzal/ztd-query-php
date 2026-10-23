<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json\Path;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A JSON path step with only the operands its selection operation requires.
 *
 * @visibility public
 * @example Refusing an array step without its index
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind::Cell) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Leg
{
    use Snapshot;

    /**
     * @param Kind $kind The selection operation
     * @param string|null $name The object member, present only for Member
     * @param Index|null $from The cell or first range endpoint
     * @param Index|null $to The last range endpoint
     */
    public function __construct(public readonly Kind $kind, public readonly ?string $name = null, public readonly ?Index $from = null, public readonly ?Index $to = null)
    {
        Check::input(($kind === Kind::Member) === ($name !== null), 'Only a member step has a name.');
        Check::input($name === null || preg_match('//u', $name) === 1, 'A JSON member name is valid UTF-8.');
        Check::input(in_array($kind, [Kind::Cell, Kind::Range], true) === ($from !== null), 'A cell or range has a starting index.');
        Check::input(($kind === Kind::Range) === ($to !== null), 'Only a range has a final index.');
        Check::input($from === null || $to === null || $from->last !== $to->last || ($from->last ? $from->offset >= $to->offset : $from->offset <= $to->offset), 'A JSON path range runs forward when its endpoints count from the same end.');
    }
}
