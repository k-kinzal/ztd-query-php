<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlFixture\Syntax\NumericLiteral;
use SqlFixture\Syntax\QuotedText;
use SqlParser\Parser\Node;

/**
 * Interprets the DEFAULT constraint of a column, keeping expressions as SQL text.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Returns the literal value a DEFAULT constraint declares, or the text of its expression.
     */
    public function extractDefault(Node $constraint, string $sql): int|float|bool|string|null
    {
        $reader = new NodeReader();
        $tokens = array_slice($constraint->tokens(), 1);
        $sign = '';
        $first = $tokens[0] ?? null;
        if ($first !== null && ($first->is('PLUS') || $first->is('MINUS'))) {
            $sign = $first->text;
            array_shift($tokens);
        }
        $value = $tokens[0] ?? null;
        if ($value === null) {
            return null;
        }
        if (count($tokens) > 1) {
            return $reader->textOf($tokens, $sql);
        }

        return match ($value->name) {
            'INTEGER', 'FLOAT' => (new NumericLiteral())->decode($sign . $value->text),
            'STRING' => (new QuotedText())->unquote($value->text),
            'NULL' => null,
            'ID' => $this->identifierValue($value->text),
            default => $reader->textOf($tokens, $sql),
        };
    }

    /**
     * Reads a bare word default: TRUE and FALSE become booleans, a quoted word is its text.
     */
    public function identifierValue(string $text): bool|string
    {
        if (str_starts_with($text, '"')) {
            return (new QuotedText())->unquote($text);
        }

        return match (strtoupper($text)) {
            'TRUE' => true,
            'FALSE' => false,
            default => $text,
        };
    }
}
