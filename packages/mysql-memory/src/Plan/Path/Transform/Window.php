<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Path\Transform;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Plan\Path\AccessPath;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

/**
 * Computes the window functions of one window over the rows of its input.
 *
 * The rows are sorted by the PARTITION BY expressions, ascending, and then by the ORDER BY
 * expressions of the window; rows equal on every key keep their input order, and a window without
 * either keeps the input order. Arguments with effects are stored once as their rows enter
 * the window buffer; other expressions are evaluated when the window reads them.
 * Each output row is the input row followed by the value of each
 * window function. A window without functions only sorts, as the server sorts the rows for the
 * ORDER BY of the query before windows that need no sorting.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html.
 *
 * @visibility MySqlMemory
 */
final class Window implements AccessPath
{
    /**
     * @param AccessPath $input The rows read
     * @param list<Evaluable> $partition The PARTITION BY expressions
     * @param list<array{Evaluable, bool}> $order The ORDER BY expressions, each with whether it is descending
     * @param WindowFrame $frame The frame of the window
     * @param list<Analytic> $functions The window functions computed over the window, reading the arguments after the input row
     * @param list<Evaluable> $arguments The arguments of the window functions, evaluated once for each input row
     */
    public function __construct(
        public readonly AccessPath $input,
        public readonly array $partition,
        public readonly array $order,
        public readonly WindowFrame $frame,
        public readonly array $functions,
        public readonly array $arguments = [],
    ) {
    }

    /**
     * Tells whether this window needs a complete partition before producing output.
     */
    public function buffered(): bool
    {
        if ($this->frame->unit === FrameUnit::Range) {
            return true;
        }
        foreach ($this->functions as $analytic) {
            if (in_array($analytic->kind, [WindowFunctionKind::Lead, WindowFunctionKind::Lag, WindowFunctionKind::Tile, WindowFunctionKind::CumulativeDistribution, WindowFunctionKind::PercentRank], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the width of the input and one value per window function.
     */
    #[Override]
    public function width(): int
    {
        return $this->input->width() + count($this->functions);
    }
}
