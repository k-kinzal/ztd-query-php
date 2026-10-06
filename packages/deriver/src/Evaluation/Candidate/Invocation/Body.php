<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Invocation;

use Deriver\Evaluation\Candidate\Context;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Value\Term;

/**
 * The selected implementation, shared by every dependency on a call.
 * @visibility root
 */
final class Body
{
    /**
     * Captures replacement semantics independently of the requested output.
     */
    public function __construct(public readonly Graph|Term|null $implementation, public readonly bool $replacement)
    {
    }

    /**
     * Records source expansion only when the selected source is demanded.
     */
    public function enter(Context $context): void
    {
        if (!$this->replacement && $this->implementation instanceof Graph) {
            $context->bodyExpansions++;
            $symbol = $this->implementation->body->symbol;
            $context->bodies[$symbol] = ($context->bodies[$symbol] ?? 0) + 1;
        }
    }
}
