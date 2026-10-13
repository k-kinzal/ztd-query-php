<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Spelling\Layout;

/**
 * Checks the intrinsic alignment of a projected expression and its spelling.
 *
 * One layout token belongs to each rendered piece of the expression. Quoting
 * names or strings for a different profile changes their spelling, not the
 * number of pieces. This check needs no parser or execution context. Token
 * equivalence under the actual language profile remains a publication check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ItemLayouts
{
    /**
     * Refuses a layout with no one-to-one correspondence to the expression.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the token counts differ
     */
    public function check(Scalar $expression, ?Layout $layout): void
    {
        if ($layout === null) {
            return;
        }
        $output = new Output(new Codec(GrammarRelease::MySql847));
        $expression->render($output);
        Check::input(count($layout->tokens) === count($output->canonical()), 'A select-item layout has one token per expression token.');
    }
}
