<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * A window specification: an optional existing window name, partitioning, ordering and frame.
 *
 * It is written after OVER and in the WINDOW clause. The function-like expression
 * family provides the structure; the query family holds it in named windows
 * and derives it there with deriveWindow().
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html.
 *
 * @visibility public
 * @example Holding a specification as the window of a call
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber, [], new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     $call->over instanceof \SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification // => true
 */
interface WindowSpecification extends Node
{
    /**
     * Derives every expression the specification holds, the partitioning, the ordering and the frame offsets, at the position of the window.
     */
    public function deriveWindow(Derivation $derivation, Environment $environment): void;
}
