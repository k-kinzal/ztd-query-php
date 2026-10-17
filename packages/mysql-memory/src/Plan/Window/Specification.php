<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Scalar;

/**
 * One window of a query block, a named window of its WINDOW clause or one written after OVER, with what it inherits from the window it refines.
 *
 * A window that refines another takes the partitioning of that window, and its ordering when it
 * has none of its own; the frame is always its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 *
 * @visibility MySqlMemory
 */
final class Specification
{
    /**
     * The name the server gives a window written after OVER in its messages.
     */
    public const UNNAMED = '<unnamed window>';

    /**
     * @var list<Scalar> The window function calls computed over the window, in written order
     */
    public array $calls = [];

    /**
     * @param string $name The name of the window, or UNNAMED
     * @param WindowSpec $node The window as written
     * @param list<OrderItem> $partition The PARTITION BY items, its own or inherited
     * @param list<OrderItem> $order The ORDER BY items, its own or inherited
     */
    public function __construct(public readonly string $name, public readonly WindowSpec $node, public readonly array $partition, public readonly array $order)
    {
    }

    /**
     * Answers the frame clause of the window, if it has one.
     */
    public function frame(): ?Frame
    {
        return $this->node->frame;
    }
}
