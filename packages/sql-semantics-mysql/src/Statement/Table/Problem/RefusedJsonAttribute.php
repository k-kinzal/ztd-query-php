<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A decoded engine attribute does not denote a permitted JSON document.
 *
 * The location is a byte position inside the decoded operand, not input SQL.
 *
 * @visibility public
 * @example Reading the refused operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('bad'), 'Invalid value.', 0))->message() // => 'Invalid JSON engine attribute at byte 0: Invalid value.'
 */
final class RefusedJsonAttribute implements Diagnostic
{
    use Snapshot;

    /**
     * @param Text $attribute The decoded attribute document
     * @param string $reason The violated JSON rule
     * @param int $position The byte boundary at which the rule fails
     * @param bool $deep Whether the document exceeds the nesting bound
     */
    public function __construct(public readonly Text $attribute, public readonly string $reason, public readonly int $position, public readonly bool $deep = false)
    {
        Check::input($position >= 0 && $position <= strlen($attribute->value), 'A JSON condition identifies a byte boundary of its operand.');
    }

    /**
     * Describes the operand's structural failure independently of execution.
     */
    public function message(): string
    {
        return 'Invalid JSON engine attribute at byte ' . $this->position . ': ' . $this->reason;
    }
}
