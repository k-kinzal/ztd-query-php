<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Transaction as Statement;
use SqlSemantics\Model\Transaction;

/**

 * Classifies transaction control by the operands each command requires. @visibility SqlSemantics

 */
final class TransactionBinder
{
    public function bind(Origin $origin, Node $node, Scope $scope): ?BoundStatement
    {
        $tokens = $node->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        $kind = $words[0] ?? '';
        if (in_array($kind, ['BEGIN', 'START'], true) && ($kind !== 'START' || ($words[1] ?? '') === 'TRANSACTION')) {
            $mode = Transaction\Mode::tryFrom($words[1] ?? '');
            return new Statement\BeginTransactionStatement($origin, $mode, $this->characteristics($words));
        }
        if ($scope->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql && in_array($kind, ['PREPARE', 'COMMIT', 'ROLLBACK'], true) && in_array($words[1] ?? '', ['PREPARED', 'TRANSACTION'], true) && isset($tokens[2]) && $tokens[2]->text[0] === "'") {
            $id = (new \SqlSemantics\Binding\LiteralBinder($scope->identifiers->dialect))->bind($tokens[2]);
            if (!$id instanceof \SqlSemantics\Model\Scalar\Value\Literal) {
                Tree::invalid($node, 'prepared transaction identifier');
            }
            return match ($kind) {
                'PREPARE' => new Statement\PrepareTransactionStatement($origin, $id),
                'COMMIT' => new Statement\CommitPreparedStatement($origin, $id),
                'ROLLBACK' => new Statement\RollbackPreparedStatement($origin, $id),
            };
        }
        if (in_array($kind, ['SAVEPOINT', 'RELEASE'], true) || ($kind === 'ROLLBACK' && in_array('TO', $words, true))) {
            $last = $tokens[count($tokens) - 1];
            $name = $scope->identifiers->name($last);
            return match ($kind) {
                'SAVEPOINT' => new Statement\SavepointStatement($origin, $name),
                'RELEASE' => new Statement\ReleaseSavepointStatement($origin, $name),
                'ROLLBACK' => new Statement\RollbackToSavepointStatement($origin, $name),
            };
        }
        if (in_array($kind, ['COMMIT', 'END', 'ROLLBACK', 'ABORT'], true)) {
            $text = implode(' ', $words);
            $chain = str_contains($text, 'NO CHAIN') ? Transaction\Chaining::NoChain : (str_contains($text, 'CHAIN') ? Transaction\Chaining::Chain : Transaction\Chaining::Default);
            $release = str_contains($text, 'NO RELEASE') ? Transaction\Release::NoRelease : (str_contains($text, 'RELEASE') ? Transaction\Release::Release : Transaction\Release::Default);
            return in_array($kind, ['COMMIT', 'END'], true) ? new Statement\CommitTransactionStatement($origin, $chain, $release) : new Statement\RollbackTransactionStatement($origin, $chain, $release);
        }
        return null;
    }

    /**

     * @param list<string> $words

     */
    public function characteristics(array $words): Transaction\Characteristics
    {
        $text = implode(' ', $words);
        $isolation = null;
        foreach (Transaction\Isolation::cases() as $candidate) {
            if (str_contains($text, 'ISOLATION LEVEL ' . $candidate->value)) {
                $isolation = $candidate;
            }
        }
        $access = str_contains($text, 'READ ONLY') ? Transaction\Access::ReadOnly : (str_contains($text, 'READ WRITE') ? Transaction\Access::ReadWrite : null);
        $deferrable = str_contains($text, 'NOT DEFERRABLE') ? false : (str_contains($text, 'DEFERRABLE') ? true : null);
        return new Transaction\Characteristics($isolation, $access, $deferrable, str_contains($text, 'WITH CONSISTENT SNAPSHOT'));
    }
}
