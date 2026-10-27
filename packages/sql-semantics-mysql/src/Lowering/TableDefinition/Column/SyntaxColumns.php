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
            $definition = null;
            if ($node->name === 'opt_column_attribute_list') {
                (new ColumnRule($lowering))->attributes($node);
            } elseif ($legacy && $node->name === 'field_ident') {
                $lowering->names->columnName($node);
            } elseif ($legacy && $node->name === 'field_spec') {
                $definition = $lowering->tableDefinitions->tableElement($node);
            } elseif ($node->name === 'create_table_stmt') {
                $definition = $lowering->tableDefinitions->statement($node);
            } elseif ($node->name === 'alter_table_stmt') {
                $definition = $lowering->tableChanges->statement($node);
            }
            return $definition === null ? null : $this->attributes($definition, $lowering, $legacy);
        } catch (RejectedInput $failure) {
            return $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName
                ? new ParseFailure($failure->problem, $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize || $failure->aborts) : null;
        } catch (\SqlSemantics\Diagnostic\AnalysisException) {
            // Other refusals retain their own declaration and notice boundaries.
        }
        return null;
    }

    /**
     * Reads the retained columns at the grammar boundary reached by an incomplete input.
     */
    public function attributes(\SqlSemantics\Statement\Node $definition, Lowering $lowering, bool $legacy): ?ParseFailure
    {
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->nodes($definition) as $column) {
            if ($column instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition && ($problem = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes())->problem($column, $lowering->programName ?? $lowering->columnTable)) !== null) {
                return new ParseFailure($problem, true, $legacy ? \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax : \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
            }
        }
        return null;
    }
}
