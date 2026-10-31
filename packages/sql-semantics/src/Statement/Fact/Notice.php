<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * A warning and the SQL occurrence and stored instruction it belongs to.
 *
 * @visibility public
 * @example Keeping an unlocated statement warning
 *     $warning = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryBitwise);
 *     (new \SqlSemantics\Statement\Fact\Notice($warning))->warning === $warning // => true
 */
final class Notice
{
    use Snapshot;

    /**
     * Associates a warning with its optional semantic subject and stored instruction.
     *
     * @param bool $fromInput Whether lowering fixed this spelling notice before semantic binding
     */
    public function __construct(public readonly Warning $warning, public readonly Node|Name|null $subject = null, public readonly ?Node $instruction = null, public readonly ?Span $inputSource = null, public readonly bool $fromInput = false)
    {
        \SqlSemantics\Diagnostic\Check::input(!$fromInput || ($subject !== null && $inputSource !== null), 'An input spelling notice retains its subject and input boundary.');
    }
}
