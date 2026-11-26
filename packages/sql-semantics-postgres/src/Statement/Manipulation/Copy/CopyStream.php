<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;

/**
 * The client connection as the file of COPY, written STDIN or STDOUT.
 *
 * PostgreSQL keeps no file name for either keyword: both mean the client
 * connection, whichever the direction. The spelling is kept.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility SqlSemantics
 */
enum CopyStream: string implements Node
{
    case Stdin = 'STDIN';
    case Stdout = 'STDOUT';

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value);
    }
}
