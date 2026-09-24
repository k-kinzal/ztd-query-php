<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * Reads the single literal token of a server command operand, keeping its spelling.
 * @visibility SqlSemantics
 */
final class Literals
{
    /**
     * Binds the only token of a text, number or NULL operand.
     * @throws UnclassifiedSql
     */
    public static function text(Node|Token $operand): Literal
    {
        $tokens = $operand instanceof Token ? [$operand] : $operand->tokens();
        $literal = count($tokens) === 1 ? (new LiteralBinder(Dialect::MySql))->bind($tokens[0]) : null;
        if (!$literal instanceof Literal) {
            throw new UnclassifiedSql('A server command operand requires one literal: ' . ($operand instanceof Token ? $operand->text : $operand->toString()));
        }
        return $literal;
    }
}
