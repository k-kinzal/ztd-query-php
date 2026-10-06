<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `NODEGROUP [=] id`: the NDB node group of a tablespace or log file group.
 *
 * The equals sign is optional and not written. NDB only.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2')))->group->text // => '2'
 */
final class NodegroupOption implements StorageOption
{
    use Snapshot;

    /**
     * @param Numeral $group The node group identifier
     */
    public function __construct(public readonly Numeral $group)
    {
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'NODEGROUP';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('NODEGROUP')->node($this->group);
    }
}
