<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;

/**
 * An option of the old COPY syntax written as one keyword: BINARY, FREEZE, CSV or HEADER.
 *
 * PostgreSQL turns each into a generic option: BINARY and CSV set `format`,
 * FREEZE sets `freeze`, HEADER sets `header`.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10.
 *
 * @visibility SqlSemantics
 */
enum CopyFlag: string implements Node
{
    case Binary = 'BINARY';
    case Freeze = 'FREEZE';
    case Csv = 'CSV';
    case Header = 'HEADER';

    /**
     * Answers the name of the generic option the keyword sets.
     */
    public function option(): string
    {
        return match ($this) {
            self::Binary, self::Csv => 'format',
            self::Freeze => 'freeze',
            self::Header => 'header',
        };
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value);
    }
}
