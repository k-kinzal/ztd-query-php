<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A zero file count refused by the MySQL 8.4 and later LOAD input grammar.
 *
 * Earlier releases accept zero, so the version-neutral LoadInput domain still
 * contains it. This refusal retains only the numeric input location.
 *
 * @visibility public
 * @example Reading the file-count constraint
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount(20))->message() // => 'The number of files cannot be zero'
 * @example Rejecting a negative input location
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount(-1) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ZeroFileCount implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The first byte of the zero NUM operand
     */
    public function __construct(public readonly int $offset)
    {
        Check::input($offset >= 0, 'A LOAD input operand begins at a nonnegative byte offset.');
    }

    /**
     * Describes the versioned count domain without retaining SQL.
     */
    public function message(): string
    {
        return 'The number of files cannot be zero';
    }
}
