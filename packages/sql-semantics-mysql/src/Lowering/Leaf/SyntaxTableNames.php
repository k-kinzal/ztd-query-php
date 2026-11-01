<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\SyntaxReadNames;

/**
 * Refuses table-identity operands while preserving aliases with empty names.
 * Table identities and open handler references use this rule. In particular,
 * a multiple-DELETE target can name an empty alias through table_ident_opt_wild.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxTableNames
{
    /**
     * Lowers completed, valid table identities before a later syntax refusal.
     * Parser nodes and source text are transient; only names and boundaries escape.
     *
     * @return list<array{int, \SqlSemantics\Statement\Identifier\QualifiedName}> Table dependencies in input order
     */
    public function dependencies(\SqlParser\Parser\ReductionParser $parser, string $statement, int $end, \SqlSemantics\Contract\LanguageProfile $profile): array
    {
        $lowering = new Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new \SqlSemantics\Lowering\Leaves(), $profile);
        $names = [];
        try {
            $parser->parse($statement, static function (Node $node, \SqlParser\Lexer\Token $lookahead) use ($lowering, $end, &$names): void {
                if ($node->name !== 'table_ident' || $lookahead->offset > $end) {
                    return;
                }
                $name = $lowering->names->qualified($node);
                if ((new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->problem($name) === null) {
                    $names[] = [(new \SqlSemantics\Construction\SourceRanges())->span($node, true)[1] ?? 0, $name];
                }
            });
        } catch (\SqlParser\Lexer\SourceException) {
            // The caller owns the original refusal; only completed names precede it.
        }
        return $names;
    }

    /**
     * Answers the typed refusal of a completed table-identity operand.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if (in_array($node->name, ['table_ident', 'table_ident_nodb'], true)) {
            $name = $lowering->names->qualified($node);
        } elseif ($node->name === 'handler_stmt' && $lowering->form($node)->node(1)->name === 'ident') {
            $name = new \SqlSemantics\Statement\Identifier\QualifiedName($lowering->names->identifier($lowering->form($node)->node(1)));
        } else {
            return null;
        }
        $problem = (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->problem($name);

        return $problem === null ? null : new RejectedInput($problem, (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0, in_array($lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true));
    }

    /**
     * Reads table operands before lowering a completed program member.
     */
    public function within(Node $node, Lowering $lowering): ?RejectedInput
    {
        $pending = [$node];
        while ($pending !== []) {
            $part = array_pop($pending);
            $failure = $this->rejected($part, $lowering);
            if ($failure !== null) {
                return $failure;
            }
            foreach (array_reverse($part->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return null;
    }

    /**
     * Legacy definition targets are checked while reading grammar operands.
     * MySQL 5.7 query and DML table references wait for contextualization.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens The transient input prefix, when the command is not yet complete
     */
    public function completed(Node $node, Lowering $lowering, array $tokens = []): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        if ($lowering->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744 && $this->contextual($node, $tokens)) {
            return null;
        }
        $failure = $this->rejected($node, $lowering);

        return $failure?->aborts === true ? new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($failure->problem, true) : null;
    }

    /**
     * Identifies table references owned by query and DML clauses, including stored bodies.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens Transient lexical tokens whose keyword roles are already distinguished
     */
    public function contextual(Node $node, array $tokens): bool
    {
        if ($node->name !== 'table_ident') {
            return false;
        }
        $start = $node->tokens()[0]->offset ?? -1;
        $dml = ['SELECT_SYM', 'INSERT', 'INSERT_SYM', 'UPDATE_SYM', 'DELETE_SYM', 'REPLACE', 'REPLACE_SYM'];
        $contextual = false;
        foreach ($tokens as $token) {
            if ($token->offset >= $start) {
                break;
            }
            if (in_array($token->name, [...$dml, 'ALTER', 'CREATE', 'DROP', 'LOAD'], true)) {
                $contextual = in_array($token->name, $dml, true);
            }
        }
        return $contextual;
    }

    /**
     * Reads a modern CREATE TABLE target independently of its later definition operands.
     * No source tree is retained by the returned identifier.
     */
    public function created(Node $tree, \SqlSemantics\Contract\LanguageProfile $profile): ?\SqlSemantics\Statement\Identifier\QualifiedName
    {
        $node = $tree->find('create_table_stmt')[0] ?? null;
        if ($node === null || ($tree->tokens()[0] ?? null) !== ($node->tokens()[0] ?? null)) {
            return null;
        }
        $lowering = new Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new \SqlSemantics\Lowering\Leaves(), $profile);

        return $lowering->names->qualified($lowering->form($node)->node(4));
    }

    /**
     * Reads multiple-DELETE targets independently of rejected relation operands.
     * Only the outer command owns these names; stored bodies are not inspected.
     * A completed target list must precede the supplied refusal boundary.
     *
     * @return list<\SqlSemantics\Statement\Identifier\QualifiedName> The independently lowered target names
     */
    public function deleted(Node $tree, \SqlSemantics\Contract\LanguageProfile $profile, int $end = PHP_INT_MAX): array
    {
        $node = $tree->find('delete_stmt')[0] ?? $tree->find('delete')[0] ?? null;
        if ($node === null || ($tree->tokens()[0] ?? null) !== ($node->tokens()[0] ?? null)) {
            return [];
        }
        $lowering = new Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new \SqlSemantics\Lowering\Leaves(), $profile);
        $node = $node->find('single_multi')[0] ?? $node;
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['table_alias_ref_list', 'table_wild_list'], true)) {
                return ((new \SqlSemantics\Construction\SourceRanges())->span($child)[1] ?? PHP_INT_MAX) <= $end ? $lowering->dml->deleteTargets($child) : [];
            }
        }

        return [];
    }

    /**
     * Reads outer INSERT, REPLACE and UPDATE dependencies before refused values.
     * Nested reads keep their own scope and only referenced CTEs contribute names. Returned names retain neither input nor AST.
     *
     * @return list<\SqlSemantics\Statement\Identifier\QualifiedName> Base-table names preceding the refusal
     */
    public function written(Node $tree, \SqlSemantics\Contract\LanguageProfile $profile, \SqlSemantics\Statement\Identifier\Comparison $comparison, int $end): array
    {
        $node = null;
        foreach (['insert_stmt', 'replace_stmt', 'update_stmt', 'insert', 'replace', 'update'] as $rule) {
            foreach ($tree->find($rule) as $candidate) {
                if (($tree->tokens()[0] ?? null) === ($candidate->tokens()[0] ?? null)) {
                    $node = $candidate;
                    break 2;
                }
            }
        }
        if ($node === null) {
            return [];
        }
        $lowering = new Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new \SqlSemantics\Lowering\Leaves(), $profile);
        $roots = [];
        $with = null;
        foreach ($node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($child->name === 'opt_with_clause') {
                $with = $child;
            } elseif (in_array($child->name, ['table_ident', 'insert2', 'join_table_list', 'table_reference_list'], true)) {
                $roots[] = $child;
            }
        }
        return (new SyntaxReadNames())->names($roots, $lowering, $comparison, $end, $with);
    }

}
