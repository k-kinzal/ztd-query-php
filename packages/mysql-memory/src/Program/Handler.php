<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;

/**
 * A handler in scope: its declaration, the block that declares it, and what is in scope where it runs.
 *
 * The statement of a handler runs in the scope of the block that declares it, where the
 * handlers of that block are not active: a condition the handler raises is handled by the
 * handlers of the blocks around it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler-scope.html.
 *
 * @visibility MySqlMemory
 */
final class Handler
{
    /**
     * @param HandlerDeclaration $declaration The declaration
     * @param Block $block The block that declares the handler
     * @param array{int, int, int, int} $mark What is in scope where the handler runs: the rows, conditions and cursors of its block, and the handlers of the blocks around it
     */
    public function __construct(public readonly HandlerDeclaration $declaration, public readonly Block $block, public readonly array $mark)
    {
    }
}
