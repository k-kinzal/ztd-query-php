<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Flush;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One item of FLUSH: what it flushes and, for RELAY LOGS, the replication channel.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 *
 * @visibility public
 * @example Flushing the relay logs of one channel
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem(\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption::RelayLogs, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('c1'));
 *     $item->channel?->value // => 'c1'
 */
final class FlushItem implements Node
{
    use Snapshot;

    /**
     * @param FlushOption $option What the item flushes
     * @param Text|null $channel The channel of FOR CHANNEL, written only after RELAY LOGS (MySQL 5.7 and later)
     * @throws InvalidConstruction When a channel follows another option
     */
    public function __construct(public readonly FlushOption $option, public readonly ?Text $channel = null)
    {
        Check::input($channel === null || $option === FlushOption::RelayLogs, 'FOR CHANNEL follows RELAY LOGS only.');
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->option->value));
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
