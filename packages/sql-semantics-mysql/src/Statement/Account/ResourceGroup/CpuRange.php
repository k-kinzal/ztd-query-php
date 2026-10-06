<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

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
    use Snapshot;

    /**
     * @param Numeral $first The CPU number, or the first of the range
     * @param Numeral|null $last The last CPU of the range, when one is written
     */
    public function __construct(public readonly Numeral $first, public readonly ?Numeral $last = null)
    {
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
