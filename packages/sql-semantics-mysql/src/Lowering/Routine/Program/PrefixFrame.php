<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;

/**
 * A transient lexical block of completed program operands before an input rejection.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PrefixFrame
{
    /**
     * @var list<Declaration> Completed declarations in this block
     */
    public array $declarations = [];

    /**
     * @var list<ProgramStatement|Statement> Completed statements in this block
     */
    public array $statements = [];

    /**
     * @param self|null $parent The enclosing lexical block
     */
    public function __construct(public readonly ?self $parent = null)
    {
    }

    /**
     * Builds the completed prefix through the enclosing blocks, preserving condition scope.
     */
    public function body(): Block
    {
        $body = new Block($this->declarations, $this->statements);
        for ($parent = $this->parent; $parent !== null; $parent = $parent->parent) {
            $body = new Block($parent->declarations, [...$parent->statements, $body]);
        }

        return $body;
    }
}
