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
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param PartitionOptionKind $kind The option
     * @param Name|null $name The tablespace or engine name
     * @param Numeral|null $number The node group or row count
     * @param Text|null $text The directory or comment
     * @param bool $storageKeyword Whether STORAGE precedes ENGINE
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(
        public readonly PartitionOptionKind $kind,
        public readonly ?Name $name = null,
        public readonly ?Numeral $number = null,
        public readonly ?Text $text = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly bool $equals = true,
        public readonly bool $storageKeyword = false,
    ) {
        Check::input(!$storageKeyword || $kind === PartitionOptionKind::Engine, 'Only ENGINE can be introduced by STORAGE.');
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
        if ($this->storageKeyword) {
            $out->keyword('STORAGE');
        }
        $out->keyword(...explode(' ', $this->kind->value));
        if ($this->equals) {
            $out->symbol('=');
        }
        if ($this->name !== null) {
            $out->name($this->name, NameUse::Label);
        }
        $out->node($this->number)->node($this->text);
    }
}
