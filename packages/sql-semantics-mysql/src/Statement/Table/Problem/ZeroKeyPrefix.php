<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An explicitly empty key prefix has no column-index interpretation.
 *
 * This input refusal retains the decoded column identity, not an invalid key
 * part or the source text. It also applies to unreachable stored instructions.
 *
 * @visibility public
 * @example Describing the refused prefix
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\ZeroKeyPrefix(new \SqlSemantics\Statement\Identifier\Name('a')))->message() // => "Key part 'a' length cannot be 0"
 */
final class ZeroKeyPrefix implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The decoded column whose prefix is empty
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the intrinsic prefix restriction.
     */
    public function message(): string
    {
        return "Key part '" . $this->column->value . "' length cannot be 0";
    }
}
