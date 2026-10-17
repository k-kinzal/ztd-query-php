<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Log;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `BINLOG 'str'`: applies binary log events written in base64 by mysqlbinlog.
 *
 * Mirrors SQLCOM_BINLOG_BASE64_EVENT with LEX::binlog_stmt_arg. The events
 * are opaque to the parser and kept as the decoded string. Rule:
 * MYSQL-BINLOG-001. The statement has no facts of its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/binlog.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Holding the encoded events
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("binlog 'AAAA'")->statement->events->value // => 'AAAA'
 */
final class BinlogEvent implements Statement
{
    use Snapshot;

    /**
     * @param Text $events The base64 text of the events
     */
    public function __construct(public readonly Text $events)
    {
        \SqlSemantics\Diagnostic\Check::input($events->radix === null, 'Encoded binary log events are quoted text.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records nothing: the events are opaque to the parser.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('BINLOG')->node($this->events);
    }
}
