<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;

/**
 * Reads a PostgreSQL Sconst as a text literal that keeps its written spelling.
 * @visibility SqlSemantics
 */
final class StringConstants
{
    /**
     * Standard, escape, Unicode and dollar-quoted constants all bind as text literals.
     * @throws UnclassifiedSql
     */
    public static function literal(Node $constant): Literal
    {
        $token = $constant->tokens()[0] ?? throw new UnclassifiedSql('A string constant requires its token.');
        $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($token);
        if (!$literal instanceof Literal || $literal->literalKind !== LiteralKind::Text) {
            throw new UnclassifiedSql('Unclassified string constant: ' . $token->text);
        }
        return $literal;
    }
}
