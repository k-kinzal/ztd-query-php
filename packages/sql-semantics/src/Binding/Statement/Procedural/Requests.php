<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\ImportTableStatement;
use SqlSemantics\Model\Statement\Locking\LockInstanceStatement;
use SqlSemantics\Model\Statement\Locking\UnlockInstanceStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\HelpStatement;

/**
 * Binds the MySQL requests whose only operands are names and text: HELP, IMPORT TABLE and instance backup locks.
 * @visibility SqlSemantics
 */
final class Requests
{
    /**
     * Reads the searched topic from its identifier or string spelling.
     */
    public static function help(Origin $origin, Node $node, QueryContext $context): HelpStatement
    {
        $topic = Tree::child($node, ['ident_or_text']) ?? Tree::invalid($node, 'help topic');
        $tokens = $topic->tokens();
        return new HelpStatement($origin, MySqlNames::read($tokens[0] ?? Tree::invalid($node, 'help topic'), $context->tables->identifiers));
    }

    /**
     * Keeps each file pattern as its original text literal.
     */
    public static function import(Origin $origin, Node $node): ImportTableStatement
    {
        $files = array_map(static fn (Node $file): Literal => self::text($file), Tree::outer($node, ['TEXT_STRING_sys']));
        return new ImportTableStatement($origin, $files);
    }

    /**
     * Binds the instance backup lock and its release; table locks and UNLOCK TABLES belong to other binders.
     */
    public static function instance(Origin $origin, Node $node): ?BoundStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $node->tokens());
        if (($words[1] ?? '') !== 'INSTANCE') {
            return null;
        }
        return $words[0] === 'LOCK' ? new LockInstanceStatement($origin) : new UnlockInstanceStatement($origin);
    }

    /**
     * Binds a single-token string operand as a text literal.
     */
    public static function text(Node $node): Literal
    {
        $token = $node->tokens()[0] ?? Tree::invalid($node, 'text literal');
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        return $literal instanceof Literal ? $literal : Tree::invalid($node, 'text literal');
    }
}
