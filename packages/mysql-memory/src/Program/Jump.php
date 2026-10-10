<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;

/**
 * A transfer of control out of the statement that ran: to the end or the start of a labeled statement, out of a function, or out of the block of an EXIT handler.
 *
 * @visibility MySqlMemory
 */
final class Jump
{
    /**
     * @param Flow $flow Where control goes
     * @param string|null $label The label LEAVE or ITERATE names
     * @param Block|null $block The block an EXIT handler ends
     * @param int|float|string|null $value The value RETURN answers
     * @param Domain|null $domain The type of the value RETURN answers
     */
    public function __construct(public readonly Flow $flow, public readonly ?string $label = null, public readonly ?Block $block = null, public readonly int|float|string|null $value = null, public readonly ?Domain $domain = null)
    {
    }

    /**
     * Tells whether the jump ends a block or loop with a label: LEAVE of its label, or the end of the block an EXIT handler is declared in.
     */
    public function ends(?string $label, ?Block $block): bool
    {
        return match ($this->flow) {
            Flow::Leave => $label !== null && $this->label !== null && strcasecmp($this->label, $label) === 0,
            Flow::Exit => $block !== null && $this->block === $block,
            Flow::Iterate, Flow::Return, Flow::Resume => false,
        };
    }

    /**
     * Tells whether the jump starts a loop with a label again.
     */
    public function repeats(?string $label): bool
    {
        return $this->flow === Flow::Iterate && $label !== null && $this->label !== null && strcasecmp($this->label, $label) === 0;
    }
}
