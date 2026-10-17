<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use Closure;
use SqlSemantics\Statement\Node;

/**
 * Tracks nested stored instructions during one derivation.
 *
 * Program members and ordinary SQL statements share instruction ownership.
 * Leaving a failed derivation restores its enclosing instruction as well.
 *
 * @visibility SqlSemantics
 */
final class InstructionScope
{
    /**
     * @var list<Node> Stored instructions in nesting order
     */
    private array $members = [];

    /**
     * @param Closure(): void $derive The member's language-specific derivation
     * @param-immediately-invoked-callable $derive
     */
    public function within(Node $member, Closure $derive): void
    {
        $this->members[] = $member;
        try {
            $derive();
        } finally {
            array_pop($this->members);
        }
    }

    /**
     * Answers the innermost instruction, or null outside a stored definition.
     */
    public function current(): ?Node
    {
        return $this->members === [] ? null : $this->members[count($this->members) - 1];
    }
}
