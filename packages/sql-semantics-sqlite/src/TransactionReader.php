<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\CommitKeyword;
use SqlSemantics\Statement\Transaction\LockAcquisition;
use SqlSemantics\Statement\Transaction\ReleaseSavepoint;
use SqlSemantics\Statement\Transaction\Rollback;
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\Savepoint;
use SqlSemantics\Statement\Transaction\TransactionName;

/**
 * Lowers transaction commands into operations, discarding parser production structure.
 * @visibility SqlSemantics
 */
final class TransactionReader
{
    /**
     * Describes the requested operation without assuming a transaction or savepoint exists.
     */
    public function read(Node $command): Begin|Commit|Rollback|Savepoint|ReleaseSavepoint|RollbackToSavepoint
    {
        $tokens = $command->tokens();
        $keyword = strtoupper($tokens[0]->text ?? '');
        $transaction = Tree::child($command, ['trans_opt']);
        $target = Tree::child($command, ['nm']);
        $mode = Tree::child($command, ['transtype']);
        $locks = $mode === null ? LockAcquisition::Default : LockAcquisition::from(strtoupper(Tree::text($mode)));
        $name = $transaction === null ? new TransactionName() : new TransactionName($this->optionalName($transaction), true);
        return match ($keyword) {
            'BEGIN' => new Begin($locks, $name),
            'COMMIT', 'END' => new Commit($name, CommitKeyword::from($keyword)),
            'ROLLBACK' => $target === null ? new Rollback($name) : new RollbackToSavepoint($this->name($target), $name, Tree::child($command, ['savepoint_opt']) !== null),
            'SAVEPOINT' => new Savepoint($this->requiredName($target, $command)),
            'RELEASE' => new ReleaseSavepoint($this->requiredName($target, $command), Tree::child($command, ['savepoint_opt']) !== null),
            default => Tree::unsupported($command, 'transaction operation'),
        };
    }

    /**
     * Reads a transaction label where the grammar permits one.
     */
    public function optionalName(Node $node): ?Name
    {
        $name = Tree::child($node, ['nm']);
        return $name === null ? null : $this->name($name);
    }

    /**
     * Requires the named target of a savepoint operation.
     */
    public function requiredName(?Node $node, Node $command): Name
    {
        if ($node === null) {
            Tree::unsupported($command, 'savepoint target');
        }
        return $this->name($node);
    }

    /**
     * Reads the identifier's value and quoting convention, without retaining its syntax.
     */
    public function name(Node $node): Name
    {
        return (new IdentifierReader())->name($node);
    }
}
