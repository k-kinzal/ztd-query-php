<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\OperationRules;
use SqlSemantics\Platform\PostgreSql\NameRules;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Script\Sequence;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\CommitKeyword;
use SqlSemantics\Statement\Transaction\ReleaseSavepoint;
use SqlSemantics\Statement\Transaction\Rollback;
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\Savepoint;

/**
 * Lowers statement boundaries into operations without grammar-shaped fallback values.
 * @visibility SqlSemantics
 */
final class OperationReader implements OperationRules
{
    /**
     * Reads complete input against the same supplied declaration context.
     */
    public function read(Node $source, Catalog $catalog): Operation
    {
        $boundaries = Tree::outer($source, ['toplevel_stmt']);
        $operations = [];
        foreach ($boundaries as $boundary) {
            if ($boundary->tokens() === []) {
                continue;
            }
            $command = Tree::child($boundary, ['TransactionStmtLegacy']);
            if ($command === null) {
                $statement = Tree::child($boundary, ['stmt']);
                $command = $statement === null ? null : Tree::child($statement, ['TransactionStmt']);
            }
            if ($command === null) {
                Tree::unsupported($boundary, 'semantic operation');
            }
            $operations[] = $this->transaction($command);
        }
        return count($operations) === 1 ? $operations[0] : new Sequence(...$operations);
    }

    /**
     * Describes transaction requests without tracking an execution history.
     */
    public function transaction(Node $source): Operation
    {
        Tree::assertChildren($source, ['opt_transaction', 'ColId'], ['BEGIN', 'START', 'TRANSACTION', 'COMMIT', 'END', 'ABORT', 'ROLLBACK', 'TO', 'SAVEPOINT', 'RELEASE']);
        $tokens = $source->tokens();
        $keyword = strtoupper($tokens[0]->text);
        $identifier = Tree::child($source, ['ColId']);
        $name = $identifier === null ? null : $this->identifier($identifier);
        assert($name !== null || !in_array($keyword, ['SAVEPOINT', 'RELEASE'], true), 'A savepoint operation has a target name.');
        return match ($keyword) {
            'BEGIN', 'START' => new Begin(),
            'COMMIT' => new Commit(),
            'END' => new Commit(keyword: CommitKeyword::End),
            'ROLLBACK', 'ABORT' => $name === null ? new Rollback() : new RollbackToSavepoint($name),
            'SAVEPOINT' => new Savepoint($name),
            'RELEASE' => new ReleaseSavepoint($name),
            default => Tree::unsupported($source, 'semantic operation'),
        };
    }

    /**
     * Decodes a name only after the grammar establishes its identifier role.
     */
    public function identifier(Node $source): Name
    {
        $tokens = $source->tokens();
        assert(count($tokens) === 1, 'An identifier occupies one token.');
        return new Name((new NameRules())->name($tokens[0]), Quote::tryFrom(substr($tokens[0]->text, 0, 1)) ?? Quote::None);
    }

}
