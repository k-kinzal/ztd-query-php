<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The client connection as the file of COPY, written STDIN or STDOUT.
 *
 * PostgreSQL keeps no file name for either keyword: both mean the client
 * connection, whichever the direction. The spelling is kept.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html.
 *
 * @visibility public
 * @example Copying to the client
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('COPY t TO STDOUT');
 *     $copy->statement->file // => \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyStream::Stdout
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
