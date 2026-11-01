<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Definition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\InputPositions;
use SqlSemantics\Statement\Snapshot;

/**
 * Input boundaries used to format a stored-program declaration's diagnostics.
 *
 * These positions never determine the body's meaning. A directly constructed
 * declaration needs no source; its conditions are derived from the same operands.
 *
 * @visibility public
 * @example Retaining parameter completion positions without SQL text
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource(18, [24, 31]))->typeEnds // => [24, 31]
 */
final class DefinitionSource
{
    use Snapshot;

    /**
     * @var list<int> Parameter completions followed by the function return declaration
     */
    public readonly array $typeEnds;

    /**
     * @param int $nameEnd The end of the declared name
     * @param list<int> $typeEnds Completed parameter and return-type boundaries, or an empty list without input
     * @param int|null $bodyRefusalOffset The token at which a language/body mismatch is reported; null for end of input
     */
    public function __construct(public readonly int $nameEnd = 0, array $typeEnds = [], public readonly ?int $bodyRefusalOffset = null)
    {
        Check::input($nameEnd >= 0 && ($bodyRefusalOffset === null || $bodyRefusalOffset >= 0), 'Definition source positions are nonnegative.');
        $this->typeEnds = (new InputPositions())->checked($typeEnds);
    }
}
