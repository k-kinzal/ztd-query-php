<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\CursorDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\DeclarationOrder;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds BEGIN ... END blocks: declarations in order, each visible to later declarations and to the block's statements.
 * @visibility SqlSemantics
 */
final class Blocks
{
    /**
     * Binds a labeled or unlabeled block.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $node, ProgramFrame $frame): BlockStatement
    {
        $content = Tree::child($node, ['sp_block_content']) ?? throw new UnclassifiedSql('A block requires BEGIN ... END.');
        $label = Loops::label($node, $frame);
        $inner = $label === null ? $frame : $frame->withLabel($label, false);
        $declarations = [];
        $handled = [];
        $rank = 0;
        $names = [];
        foreach (Tree::outer(Tree::child($content, ['sp_decls']) ?? $content, ['sp_decl']) as $declare) {
            [$declaration, $inner] = Declarations::bind($declare, $inner, $handled);
            $current = $declaration instanceof CursorDeclaration ? 1 : ($declaration instanceof HandlerDeclaration ? 2 : 0);
            if ($current < $rank) {
                throw new InvalidSql(InputViolation::ProgramDeclaration, $declare);
            }
            $rank = $current;
            foreach (DeclarationOrder::names($declaration) as $name) {
                if (isset($names[$name])) {
                    throw new InvalidSql(InputViolation::ProgramDeclaration, $declare);
                }
                $names[$name] = true;
            }
            $declarations[] = $declaration;
        }
        return new BlockStatement($label, $declarations, ProgramBinder::list(Tree::child($content, ['sp_proc_stmts']), $inner));
    }
}
