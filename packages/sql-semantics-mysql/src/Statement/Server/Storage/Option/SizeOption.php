<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A size option: `INITIAL_SIZE [=] size`, `MAX_SIZE [=] size`, `FILE_BLOCK_SIZE [=] size`, ….
 *
 * The size is a number of bytes or a word with a K, M or G multiplier. The
 * equals sign is optional and not written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding an initial size written with a multiplier
 *     $option = new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption(\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind::Initial, new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(null, new \SqlSemantics\Statement\Identifier\Name('16M')));
 *     [$option->keyword(), $option->size->word?->value] // => ['INITIAL_SIZE', '16M']
 */
final class SizeOption implements StorageOption
{
    use Snapshot;

    /**
     * @param SizeOptionKind $kind The option
     * @param ByteSize $size The size
     */
    public function __construct(public readonly SizeOptionKind $kind, public readonly ByteSize $size)
    {
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->size);
    }
}
