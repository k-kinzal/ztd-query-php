<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;

/**
 * Interprets the default expression text PostgreSQL's catalog reports for a column.
 *
 * The catalog hands back the expression as SQL text, so it is parsed as the
 * single target of a SELECT and interpreted like a DEFAULT clause.
 *
 * @visibility root
 */
final class CatalogExpression
{
    /**
     * Keeps the grammar used to parse each catalog expression.
     */
    public function __construct(private readonly PostgreSqlParser $parser)
    {
    }

    /**
     * Returns the literal value the expression denotes, or its text for anything else.
     */
    public function evaluate(string $expression): int|float|bool|string|null
    {
        $sql = 'SELECT ' . $expression;
        $node = $this->expression($sql);

        return $node === null ? $expression : (new DefaultExpression())->evaluate($node, $sql);
    }

    /**
     * Reports whether the expression draws its value from a sequence.
     */
    public function isSequence(string $expression): bool
    {
        $node = $this->expression('SELECT ' . $expression);

        return $node !== null && (new DefaultExpression())->isSequenceCall($node);
    }

    /**
     * Returns the a_expr node of a single-target SELECT, or null when the text is not one expression.
     */
    public function expression(string $sql): ?Node
    {
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException) {
            return null;
        }
        $targets = $tree->find('target_el');
        if (count($targets) !== 1) {
            return null;
        }

        return (new NodeReader())->child($targets[0], 'a_expr');
    }
}
