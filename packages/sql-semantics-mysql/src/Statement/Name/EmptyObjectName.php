<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An empty table name, explicit database qualifier or created server name is invalid.
 *
 * The role supplies the diagnostic meaning; no invalid statement or SQL is retained.
 * Observed on native MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Describing an empty table operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName(\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Table))->message() // => "Incorrect table name ''"
 */
final class EmptyObjectName implements Diagnostic
{
    use Snapshot;

    /**
     * @param ObjectNameKind $kind The role whose empty name was refused
     */
    public function __construct(public readonly ObjectNameKind $kind)
    {
    }

    /**
     * Describes the empty object identifier.
     */
    public function message(): string
    {
        return $this->kind === ObjectNameKind::Server ? "Incorrect server name value: ''" : 'Incorrect ' . $this->kind->value . " name ''";
    }
}
