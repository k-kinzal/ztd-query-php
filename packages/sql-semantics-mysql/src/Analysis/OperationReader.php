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
use SqlSemantics\Statement\Transaction\Commit;
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
        Tree::assertChildren($source, ['opt_work', 'opt_savepoint', 'ident'], ['BEGIN', 'COMMIT', 'ROLLBACK', 'TO', 'SAVEPOINT', 'RELEASE']);
        $identifier = Tree::child($source, ['ident']);
        $name = $identifier === null ? null : $this->identifier($identifier);
        assert($name !== null || !in_array($source->name, ['savepoint', 'release'], true), 'A savepoint operation has a target name.');
        return match ($source->name) {
            'begin', 'begin_stmt' => new Begin(),
            'commit' => new Commit(),
            'rollback' => $name === null ? new Rollback() : new RollbackToSavepoint($name, explicitSavepoint: Tree::child($source, ['opt_savepoint']) !== null),
            'savepoint' => new Savepoint($name),
            'release' => new ReleaseSavepoint($name, true),
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
