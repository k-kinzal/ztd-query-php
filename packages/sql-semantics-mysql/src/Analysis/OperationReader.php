<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\OperationRules;
use SqlSemantics\Platform\MySql\NameRules;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Script\Sequence;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\MySql\Access;
use SqlSemantics\Statement\Transaction\MySql\Commit;
use SqlSemantics\Statement\Transaction\MySql\Rollback;
use SqlSemantics\Statement\Transaction\MySql\Start;
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
     * Reads complete input against the same supplied declaration context.
     */
    public function read(Node $source, Catalog $catalog): Operation
    {
        $boundaries = Tree::outer($source, ['simple_statement_or_begin', 'verb_clause']);
        if ($boundaries === []) {
            Tree::assertChildren($source, ['sql_statement'], ['', ';']);
            $meaningful = array_filter($source->tokens(), static fn (\SqlParser\Lexer\Token $token): bool => $token->text !== '' && $token->text !== ';');
            if ($meaningful !== []) {
                Tree::unsupported($source, 'semantic operation');
            }
            return new Sequence();
        }
        assert(count($boundaries) === 1, 'The MySQL grammar input has one statement boundary.');
        $boundary = $boundaries[0];
        $commands = Tree::outer($boundary, ['begin_stmt', 'begin', 'simple_statement', 'statement']);
        assert(count($commands) === 1, 'A statement boundary has one operation.');
        $command = $commands[0];
        if (in_array($command->name, ['simple_statement', 'statement'], true)) {
            $children = Tree::significant($command);
            assert(count($children) === 1 && $children[0] instanceof Node, 'The statement chooses one operation family.');
            $command = $children[0];
        }
        return $this->transaction($command);
    }

    /**
     * Describes transaction requests without tracking an execution history.
     */
    public function transaction(Node $source): Operation
    {
        Tree::assertChildren($source, ['opt_work', 'opt_savepoint', 'ident', 'opt_chain', 'opt_release', 'opt_start_transaction_option_list'], ['BEGIN', 'START', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'TO', 'SAVEPOINT', 'RELEASE']);
        $identifier = Tree::child($source, ['ident']);
        $name = $identifier === null ? null : $this->identifier($identifier);
        assert($name !== null || !in_array($source->name, ['savepoint', 'release'], true), 'A savepoint operation has a target name.');
        return match ($source->name) {
            'begin', 'begin_stmt' => new Begin(),
            'start' => $this->start($source),
            'commit' => new Commit($this->completion(Tree::child($source, ['opt_chain'])), $this->completion(Tree::child($source, ['opt_release']))),
            'rollback' => $name === null ? new Rollback($this->completion(Tree::child($source, ['opt_chain'])), $this->completion(Tree::child($source, ['opt_release']))) : new RollbackToSavepoint($name, explicitSavepoint: Tree::child($source, ['opt_savepoint']) !== null),
            'savepoint' => new Savepoint($name),
            'release' => new ReleaseSavepoint($name, true),
            default => Tree::unsupported($source, 'semantic operation'),
        };
    }

    /**
     * Repeated characteristics combine as requests; incompatible access modes remain a contradiction.
     */
    public function start(Node $source): Start
    {
        Tree::assertChildren($source, ['opt_start_transaction_option_list'], ['START', 'TRANSACTION']);
        $snapshot = false;
        $readOnly = false;
        $readWrite = false;
        foreach (Tree::outer($source, ['start_transaction_option']) as $option) {
            $spelling = strtoupper(Tree::text($option));
            assert(in_array($spelling, ['WITH CONSISTENT SNAPSHOT', 'READ ONLY', 'READ WRITE'], true), 'Each characteristic selects one defined transaction requirement.');
            $snapshot = $snapshot || $spelling === 'WITH CONSISTENT SNAPSHOT';
            $readOnly = $readOnly || $spelling === 'READ ONLY';
            $readWrite = $readWrite || $spelling === 'READ WRITE';
        }
        $access = match (true) {
            $readOnly && $readWrite => Access::Conflicting,
            $readOnly => Access::ReadOnly,
            $readWrite => Access::ReadWrite,
            default => Access::SessionDefault,
        };
        return new Start($snapshot, $access);
    }

    /**
     * Keeps omission distinct from an explicit override of completion_type.
     */
    public function completion(?Node $source): ?bool
    {
        if ($source === null) {
            return null;
        }
        $request = strtoupper(Tree::text($source));
        assert(in_array($request, ['AND CHAIN', 'AND NO CHAIN', 'RELEASE', 'NO RELEASE'], true), 'A completion clause is a positive or negative choice.');
        return !in_array($request, ['AND NO CHAIN', 'NO RELEASE'], true);
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
