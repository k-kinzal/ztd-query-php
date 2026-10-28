<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * One CPU number or range `n-m` of the VCPU option of a resource group.
 *
 * Mirrors resourcegroups::Range. Which CPUs exist is a property of the
 * machine the server runs on, which a declaration context does not hold.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html.
 *
 * @visibility public
 * @example Holding a range
 *     $range = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3'));
 *     [$range->first->text, $range->last?->text] // => ['0', '3']
 */
final class CpuRange implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param \SqlSemantics\Platform\MySql\Statement\Literal\Numeral $first The first CPU index, including leading zeros
     * @param \SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null $last The last CPU index when a range is written
     */
    public function __construct(public readonly \SqlSemantics\Platform\MySql\Statement\Literal\Numeral $first, public readonly ?\SqlSemantics\Platform\MySql\Statement\Literal\Numeral $last = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($first->isIntegerToken() && ($last === null || $last->isIntegerToken()), 'A CPU index is an integer from 0 through 2147483647.');
    }

    /**
     * Writes the number or the range.
     */
    public function render(Output $out): void
    {
        $out->node($this->first);
        if ($this->last !== null) {
            $out->symbol('-')->node($this->last);
        }
    }
}
