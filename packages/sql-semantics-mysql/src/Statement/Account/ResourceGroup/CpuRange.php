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
 *     $range = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange(0, 3);
 *     [$range->first, $range->last] // => [0, 3]
 */
final class CpuRange implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var int<0, 2147483647> The CPU number, or the first of the range
     */
    public readonly int $first;

    /**
     * @var int<0, 2147483647>|null The last CPU, when a range is requested
     */
    public readonly ?int $last;

    /**
     * @param int $first The CPU index, within the unsigned NUM token domain
     * @param int|null $last The last CPU index, within the same domain
     */
    public function __construct(int $first, ?int $last = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($first >= 0 && $first <= 2147483647 && ($last === null || ($last >= 0 && $last <= 2147483647)), 'A CPU index is an integer from 0 through 2147483647.');
        $this->first = $first;
        $this->last = $last;
    }

    /**
     * Writes the number or the range.
     */
    public function render(Output $out): void
    {
        $out->spelled((string) $this->first);
        if ($this->last !== null) {
            $out->symbol('-')->spelled((string) $this->last);
        }
    }
}
