<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Transaction;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\InputTokens;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;

/**
 * Publishes completed top-level lock specifications before a later input refusal.
 * Temporary parser nodes are lowered immediately; only semantic operands and
 * their completion offsets leave this boundary.
 *
 * @visibility public
 * @example Preserving the first lock before a malformed second lock
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
 *     $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
 *     $locks = (new \SqlSemantics\Platform\MySql\Lowering\Server\Transaction\SyntaxLocks())->before($parser, 'LOCK TABLES t READ,?', 19, $profile);
 *     [$locks[0][1]->table->name->value, $locks[0][1]->mode->value] // => ['t', 'READ']
 */
final class SyntaxLocks
{
    /**
     * Reads completed locks in source order without retaining the source or parser nodes.
     *
     * @return list<array{int, TableLock}> Completion offsets and their semantic lock specifications
     * @throws \SqlSemantics\Construction\RejectedInput When a completed table operand is intrinsically invalid
     */
    public function before(ReductionParser $parser, string $statement, int $end, LanguageProfile $profile): array
    {
        $tokens = (new InputTokens())->prefix($parser, $statement);
        if (($tokens[0]->name ?? '') !== 'LOCK_SYM') {
            return [];
        }
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 4) . '/resources/productions/' . $profile->grammar->value . '.php'), new Leaves(), $profile);
        $locks = [];
        $failure = null;
        try {
            $parser->parse($statement, static function (Node $node, Token $lookahead) use (&$locks, &$failure, $lowering, $end): void {
                if ($failure !== null || $node->name !== 'table_lock' || $lookahead->offset > $end) {
                    return;
                }
                $failure = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->within($node, $lowering);
                if ($failure !== null) {
                    return;
                }
                $locks[] = [(new SourceRanges())->span($node)[1] ?? $lookahead->offset, (new LockRule($lowering))->lock($node)];
            });
        } catch (SourceException) {
            // Completed locks precede the original lexical or grammar refusal.
        }

        if ($failure !== null) {
            throw $failure;
        }

        return $locks;
    }
}
