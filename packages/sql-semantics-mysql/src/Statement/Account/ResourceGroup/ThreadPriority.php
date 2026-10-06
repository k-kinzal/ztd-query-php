<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The THREAD_PRIORITY of a resource group: a number with an optional minus sign.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html.
 *
 * @visibility public
 * @example Holding a negative priority
 *     $priority = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority(true, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('5'));
 *     $priority->describe() // => '-5'
 */
final class ThreadPriority implements Node
{
    use Snapshot;

    /**
     * @param bool $negative Whether a minus sign is written
     * @param Numeral $number The number as written
     */
    public function __construct(public readonly bool $negative, public readonly Numeral $number)
    {
    }

    /**
     * Answers the priority as a person reads it.
     */
    public function describe(): string
    {
        return ($this->negative ? '-' : '') . $this->number->text;
    }

    /**
     * Writes the priority.
     */
    public function render(Output $out): void
    {
        if ($this->negative) {
            $out->symbol('-')->glue();
        }
        $out->node($this->number);
    }
}
