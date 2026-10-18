<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation;

use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Term;

/**
 * The selected implementation, shared by every dependency on a call.
 * @visibility parent
 */
final class Body
{
    /**
     * Captures replacement semantics independently of the requested output.
     */
    public function __construct(public readonly BodyIndex|Term|null $implementation, public readonly bool $replacement)
    {
    }

    /**
     * Records source expansion only when the selected source is demanded.
     */
    public function enter(Context $context): void
    {
        if (!$this->replacement && $this->implementation instanceof BodyIndex) {
            $context->bodyExpansions++;
            $symbol = $this->implementation->body->symbol;
            $context->bodies[$symbol] = ($context->bodies[$symbol] ?? 0) + 1;
        }
    }
}
