<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Flush;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `FLUSH [NO_WRITE_TO_BINLOG | LOCAL] item, …`: a request to clear or reload server caches and logs.
 *
 * Mirrors SQLCOM_FLUSH with the REFRESH_* flags of LEX::type. Rule:
 * MYSQL-FLUSH-001. The items are kept in written order; the server merges
 * them into flags. LOCAL is a synonym of NO_WRITE_TO_BINLOG, which is
 * written. The statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Flushing logs and privileges
 *     $flush = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('flush local binary logs, privileges');
 *     [$flush->toString(), $flush->statement->items[1]->option] // => ['FLUSH NO_WRITE_TO_BINLOG BINARY LOGS, PRIVILEGES', \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption::Privileges]
 */
final class Flush implements Statement
{
    use Snapshot;

    /**
     * @var list<FlushItem> The items in written order; at least one
     */
    public readonly array $items;

    /**
     * @param bool $noWriteToBinlog Whether NO_WRITE_TO_BINLOG or its synonym LOCAL is written
     * @param list<FlushItem> $items The items in written order; at least one
     * @throws InvalidConstruction When the list is empty
     */
    public function __construct(public readonly bool $noWriteToBinlog, array $items)
    {
        $this->items = Check::listOf($items, FlushItem::class, 'FLUSH flushes at least one item.', 1);
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('FLUSH');
        if ($this->noWriteToBinlog) {
            $out->keyword('NO_WRITE_TO_BINLOG');
        }
        $out->list($this->items);
    }
}
