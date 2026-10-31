<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclarationNames;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

/**
 * Binds variable names as their declaration prefix completes, before its type.
 *
 * Each empty declaration list opens a lexical block. A completed block closes
 * it. Only decoded name keys survive reductions; no SQL or AST is published.
 * Native observations preserve duplicate-name priority over COLLATE, defaults
 * and malformed following input, while nested blocks may shadow outer names.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class VariablePrefix
{
    /**
 * @var list<array<string, array<string, true>>> Names in each open lexical block
 */
    private array $frames = [];

    /**
     * Consumes only the newly completed name of a left-recursive declaration list.
     */
    public function observe(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name === 'sp_decls' && $node->children === []) {
            $this->frames[] = [];
        } elseif ($node->name === 'sp_block_content') {
            array_pop($this->frames);
        }
        $index = array_key_last($this->frames);
        if ($index === null || $node->name !== 'sp_decl_idents') {
            return null;
        }
        $form = $lowering->form($node);
        $name = $lowering->names->identifier($form->node($form->signature === 'sp_decl_idents: ident' ? 0 : 2));
        $problem = (new DeclarationNames())->names([$name], $this->frames[$index], ProgramRule::DuplicateVariable);
        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
