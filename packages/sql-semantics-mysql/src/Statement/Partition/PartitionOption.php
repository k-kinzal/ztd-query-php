<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of a partition or subpartition definition, such as `ENGINE = InnoDB` or `COMMENT = 'text'`.
 *
 * Mirrors the PT_partition_option classes. Exactly one of the name, the
 * number and the text is present, as the kind requires. The `=` and the
 * STORAGE before ENGINE are optional words.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 *
 * @visibility public
 * @example Holding a partition comment
 *     $option = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption(\SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind::Comment, text: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('old rows'));
 *     $option->text?->value // => 'old rows'
 */
final class PartitionOption implements Node
{
    use Snapshot;

    /**
     * @param PartitionOptionKind $kind The option
     * @param Name|null $name The tablespace or engine name
     * @param Numeral|null $number The node group or row count
     * @param Text|null $text The directory or comment
     */
    public function __construct(
        public readonly PartitionOptionKind $kind,
        public readonly ?Name $name = null,
        public readonly ?Numeral $number = null,
        public readonly ?Text $text = null,
    ) {
        Check::input(
            ($name !== null) === $kind->named() && ($number !== null) === $kind->numbered() && ($text !== null) === (!$kind->named() && !$kind->numbered()),
            'A partition option holds exactly the value its kind takes.',
        );
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value))->symbol('=');
        if ($this->name !== null) {
            $out->name($this->name, NameUse::Label);
        }
        $out->node($this->number)->node($this->text);
    }
}
