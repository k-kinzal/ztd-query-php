<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An input declaration whose body representation contradicts its language.
 *
 * The numeric location formats an input diagnostic without retaining SQL,
 * parser nodes. A complete routine retains its body beside this condition.
 *
 * @visibility public
 * @example Reading the language constraint
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch(42))->message() // => 'The routine language and body representation disagree.'
 * @example Rejecting a negative input location
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch(-1) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class LanguageBodyMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param int|null $offset The byte offset where the incompatible body is refused; null for end of input
     */
    public function __construct(public readonly ?int $offset = null)
    {
        Check::input($offset === null || $offset >= 0, 'An input location is nonnegative.');
    }

    /**
     * Describes the relationship violated by the input declaration.
     */
    public function message(): string
    {
        return 'The routine language and body representation disagree.';
    }
}
