<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * An option of the old COPY syntax that sets a string: DELIMITER, NULL, QUOTE, ESCAPE or ENCODING followed by a string constant.
 *
 * The AS between the keyword and the string is optional.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10.
 *
 * @visibility SqlSemantics
 */
final class CopyText implements Node
{
    use Snapshot;

    /**
     * @param CopyTextKind $kind The option set
     * @param StringConstant $value The string
     */
    public function __construct(public readonly CopyTextKind $kind, public readonly StringConstant $value)
    {
    }

    /**
     * Answers the name of the generic option the item sets.
     */
    public function option(): string
    {
        return strtolower($this->kind->value);
    }

    /**
     * Writes the keyword and the string.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->value);
    }
}
