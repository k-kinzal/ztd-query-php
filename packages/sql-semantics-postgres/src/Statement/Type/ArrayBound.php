<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One dimension of an array type, with the size written for it.
 *
 * The server records the size but does not enforce it.
 * Source: https://www.postgresql.org/docs/17/arrays.html#ARRAYS-DECLARATION.
 *
 * @visibility public
 * @example Reading a declared size
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('3')))->size->digits // => '3'
 */
final class ArrayBound implements Node
{
    use Snapshot;

    /**
     * @param IntegerConstant|null $size The declared size
     */
    public function __construct(public readonly ?IntegerConstant $size = null)
    {
    }

    /**
     * Writes the brackets and the size.
     */
    public function render(Output $out): void
    {
        $out->symbol('[')->node($this->size)->symbol(']');
    }
}
