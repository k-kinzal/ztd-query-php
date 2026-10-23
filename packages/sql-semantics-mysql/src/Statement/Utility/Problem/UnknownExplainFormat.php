<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An EXPLAIN format outside the names supported by its language profile.
 *
 * The decoded name is retained even when a later statement operand cannot be parsed.
 *
 * @visibility public
 * @example Retaining an unknown format
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat(new \SqlSemantics\Statement\Identifier\Name('yaml')))->name->value // => 'yaml'
 */
final class UnknownExplainFormat implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The decoded format operand
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Describes the unsupported format category.
     */
    public function message(): string
    {
        return UtilityRule::UnknownExplainFormat->value;
    }
}
