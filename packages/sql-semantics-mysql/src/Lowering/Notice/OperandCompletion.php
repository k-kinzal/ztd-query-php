<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Notice;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Account\Privilege\LegacyColumns;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\DeclarationPrefix;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\SyntaxArrays;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\SyntaxNames;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Retains transient operand scopes while reductions complete in input order.
 *
 * Grant targets, array expressions, declaration types and SET targets retain
 * their own completion state. Only their typed notice escapes this reader.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OperandCompletion
{
    /**
     * The readers belong to one input prefix and never enter semantic values.
     */
    public function __construct(
        private readonly SyntaxNames $names = new SyntaxNames(),
        private readonly LegacyColumns $privileges = new LegacyColumns(),
        private readonly SyntaxArrays $arrays = new SyntaxArrays(),
        private readonly DeclarationPrefix $types = new DeclarationPrefix(),
        private readonly \SqlSemantics\Platform\MySql\Lowering\Routine\Program\VariablePrefix $variables = new \SqlSemantics\Platform\MySql\Lowering\Routine\Program\VariablePrefix(),
        private readonly \SqlSemantics\Platform\MySql\Lowering\TableDefinition\View\SyntaxViews $views = new \SqlSemantics\Platform\MySql\Lowering\TableDefinition\View\SyntaxViews(),
        private readonly \SqlSemantics\Platform\MySql\Lowering\Account\Authentication\SyntaxPasswords $passwords = new \SqlSemantics\Platform\MySql\Lowering\Account\Authentication\SyntaxPasswords(),
    ) {
    }

    /**
     * Interprets header operands whose completion can stop later body parsing.
     *
     * @param list<Token> $tokens Temporary tokens of the input
     */
    public function starting(Node $node, Token $lookahead, Lowering $lowering, array $tokens, \SqlParser\Parser\ReductionParser $parser, string $statement): ?\SqlSemantics\Construction\RejectedInput
    {
        $derived = $lowering->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? (new \SqlSemantics\Platform\MySql\Lowering\Query\Legacy\DerivedAliases())->rejected($node, $lowering) : null;
        return $this->views->starting($node, $lowering, $tokens)
            ?? $this->passwords->starting($node, $lookahead, $lowering, $tokens)
            ?? $derived
            ?? (new \SqlSemantics\Platform\MySql\Lowering\Replication\SyntaxLogMoment())->starting($node, $lookahead, $parser, $statement, $lowering->profile->grammar);
    }

    /**
     * Reads operand notices before the enclosing completed production.
     *
     * @param list<Token> $tokens The temporary input prefix
     */
    public function completed(Node $node, Token $lookahead, Lowering $lowering, Completion $state, array $tokens): Deprecated|ParseFailure|null
    {
        return (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxCharsets())->completed($node, $lowering, $tokens)
            ?? $this->privileges->completed($node, $lowering)
            ?? $this->arrays->completed($node, $lookahead, $lowering, $state->failedAt)
            ?? $this->variables->observe($node, $lowering)
            ?? $this->types->following($node, $lowering, $tokens)
            ?? ($state->failed ? null : $this->names->completed($node, $lowering));
    }
}
