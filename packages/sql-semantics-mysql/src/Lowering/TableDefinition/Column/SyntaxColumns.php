<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves the completion point of an intrinsic column contradiction.
 *
 * MySQL 5.x checks completed fields; modern releases check a completed table
 * statement, including a statement inside a later malformed program body.
 * The target name supplies only a default-database dependency, never an AST.
 * Verified on MySQL 5.6.51, 5.7.44 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxColumns
{
    /**
     * Records a completed table-declaration target before its column operands.
     *
     * @param list<Token> $tokens Transient tokens of the input being lowered
     */
    public function observe(Node $node, Lowering $lowering, array $tokens): void
    {
        if ($node->name !== 'table_ident') {
            return;
        }
        $start = (new \SqlSemantics\Construction\SourceRanges())->span($node)[0] ?? 0;
        $previous = null;
        foreach ($tokens as $token) {
            if ($token->offset >= $start) {
                break;
            }
            $previous = $token;
        }
        if (in_array($previous->name ?? null, ['TABLE_SYM', 'EXISTS'], true)) {
            $lowering->columnTable = $lowering->names->qualified($node);
        }
    }

    /**
     * Applies ordinary lowering at the release's column-declaration boundary.
     * Invalid names retain their own refusal boundary before a statement is constructed.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        $legacy = in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        if (in_array($node->name, ['create_table_stmt', 'alter_table_stmt'], true) && (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->within($node, $lowering) !== null) {
            return null;
        }
        try {
            if ($legacy && $node->name === 'field_spec') {
                $lowering->tableDefinitions->tableElement($node);
            } elseif ($node->name === 'create_table_stmt') {
                $lowering->tableDefinitions->statement($node);
            } elseif ($node->name === 'alter_table_stmt') {
                $lowering->tableChanges->statement($node);
            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, $failure->aborts);
        } catch (\SqlSemantics\Diagnostic\AnalysisException) {
            // Other refusals retain their own declaration and notice boundaries.
        }
        return null;
    }
}
