<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Policy\OperationRules;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\LiteralDecoder;
use SqlSemantics\Platform\PostgreSql\NameRules;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Script\Sequence;
use SqlSemantics\Statement\Transaction\Postgres\Begin;
use SqlSemantics\Statement\Transaction\Postgres\Commit;
use SqlSemantics\Statement\Transaction\Postgres\CommitPrepared;
use SqlSemantics\Statement\Transaction\Postgres\Isolation;
use SqlSemantics\Statement\Transaction\Postgres\Prepare;
use SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier;
use SqlSemantics\Statement\Transaction\Postgres\Rollback;
use SqlSemantics\Statement\Transaction\Postgres\RollbackPrepared;
use SqlSemantics\Statement\Transaction\ReleaseSavepoint;
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\Savepoint;

/**
 * Lowers statement boundaries into operations without grammar-shaped fallback values.
 * @visibility SqlSemantics
 */
final class OperationReader implements OperationRules
{
    /**
     * Uses the selected grammar for lexical decoding without retaining it in semantic operations.
     */
    public function __construct(private readonly Language $language = new Language(Dialect::PostgreSql))
    {
    }

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
        Tree::assertChildren($source, ['opt_transaction', 'ColId', 'opt_transaction_chain', 'transaction_mode_list_or_empty', 'Sconst'], ['BEGIN', 'START', 'TRANSACTION', 'COMMIT', 'END', 'ABORT', 'ROLLBACK', 'TO', 'SAVEPOINT', 'RELEASE', 'PREPARE', 'PREPARED']);
        if (Tree::child($source, ['Sconst']) !== null) {
            return $this->prepared($source);
        }
        $tokens = $source->tokens();
        $keyword = \SqlSemantics\Statement\Identifier\Ascii::upper($tokens[0]->text);
        $identifier = Tree::child($source, ['ColId']);
        $name = $identifier === null ? null : $this->identifier($identifier);
        assert($name !== null || !in_array($keyword, ['SAVEPOINT', 'RELEASE'], true), 'A savepoint operation has a target name.');
        return match ($keyword) {
            'BEGIN', 'START' => $this->begin($source),
            'COMMIT', 'END' => new Commit($this->chain(Tree::child($source, ['opt_transaction_chain']))),
            'ROLLBACK', 'ABORT' => $name === null ? new Rollback($this->chain(Tree::child($source, ['opt_transaction_chain']))) : new RollbackToSavepoint($name),
            'SAVEPOINT' => new Savepoint($name),
            'RELEASE' => new ReleaseSavepoint($name),
            default => Tree::unsupported($source, 'semantic operation'),
        };
    }

    /**
     * PostgreSQL uses the last requested value for each characteristic independently.
     */
    public function begin(Node $source): Begin
    {
        $isolation = null;
        $readOnly = null;
        $deferrable = null;
        foreach (Tree::outer($source, ['transaction_mode_item']) as $item) {
            $level = Tree::child($item, ['iso_level']);
            if ($level !== null) {
                $isolation = Isolation::from(\SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($level)));
                continue;
            }
            $choice = \SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($item));
            assert(in_array($choice, ['READ ONLY', 'READ WRITE', 'DEFERRABLE', 'NOT DEFERRABLE'], true), 'A transaction characteristic has a defined access or deferrability role.');
            if (str_starts_with($choice, 'READ ')) {
                $readOnly = $choice === 'READ ONLY';
            } else {
                $deferrable = $choice === 'DEFERRABLE';
            }
        }
        return new Begin($isolation, $readOnly, $deferrable);
    }

    /**
     * Omission and explicit NO CHAIN both request that no replacement transaction be started.
     */
    public function chain(?Node $source): bool
    {
        return $source !== null && \SqlSemantics\Statement\Identifier\Ascii::upper(Tree::text($source)) === 'AND CHAIN';
    }

    /**
     * Prepared transaction requests name decoded global identifiers and have distinct operation types.
     */
    public function prepared(Node $source): Prepare|CommitPrepared|RollbackPrepared
    {
        $literal = Tree::child($source, ['Sconst']);
        assert($literal !== null, 'A prepared transaction request names a global transaction identifier.');
        $tokens = $literal->tokens();
        assert(count($tokens) === 1, 'The lexer supplies one complete string token.');
        $identifier = new PreparedIdentifier((new LiteralDecoder($this->language))->string($tokens[0]->text));
        return match (\SqlSemantics\Statement\Identifier\Ascii::upper($source->tokens()[0]->text)) {
            'PREPARE' => new Prepare($identifier),
            'COMMIT' => new CommitPrepared($identifier),
            'ROLLBACK' => new RollbackPrepared($identifier),
            default => Tree::unsupported($source, 'prepared transaction operation'),
        };
    }

    /**
     * Decodes a name only after the grammar establishes its identifier role.
     */
    public function identifier(Node $source): Name
    {
        $tokens = $source->tokens();
        assert(count($tokens) === 1, 'An identifier occupies one token.');
        if (\SqlSemantics\Statement\Identifier\Ascii::upper(substr($tokens[0]->text, 0, 2)) === 'U&') {
            return new Name((new LiteralDecoder($this->language))->unicode($tokens[0]->text), Quote::Double);
        }
        return new Name((new NameRules())->name($tokens[0]), Quote::tryFrom(substr($tokens[0]->text, 0, 1)) ?? Quote::None);
    }

}
