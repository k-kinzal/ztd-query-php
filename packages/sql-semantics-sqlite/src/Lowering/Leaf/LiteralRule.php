<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rules\Identifiers;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TimeKeyword;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers literal tokens into exact literal values.
 *
 * Rule: SQLITE-LITERAL-001. Scope: term and the numeric token classes. A
 * numeric token loses its digit separators and becomes a decimal integer, a
 * hexadecimal integer or a floating point literal by its spelling; a string
 * is decoded; a BLOB keeps its hexadecimal digits in upper case. Each literal
 * is recorded as an operand leaf.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class LiteralRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a literal term.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function term(Node $term): Scalar
    {
        $form = $this->lowering->productions->form($term);
        $token = $form->token(0);

        return match ($form->signature) {
            'term: NULL|FLOAT|BLOB' => $this->plain($token),
            'term: STRING' => $this->lowering->leaves->record(new TextLiteral((new Identifiers())->decode($token->text))),
            'term: INTEGER', 'term: QNUMBER' => $this->number($token),
            'term: CTIME_KW' => $this->lowering->leaves->record(new CurrentTime(TimeKeyword::from(strtoupper($token->text)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the token of the NULL, floating point and BLOB class by its terminal.
     */
    public function plain(Token $token): Scalar
    {
        if ($token->name === 'NULL') {
            return $this->lowering->leaves->record(new NullLiteral());
        }
        if ($token->name === 'BLOB') {
            return $this->lowering->leaves->record(new BlobLiteral(strtoupper(substr($token->text, 2, -1))));
        }

        return $this->number($token);
    }

    /**
     * Lowers a numeric token: a decimal integer, a hexadecimal integer or a floating point literal.
     */
    public function number(Token $token): IntegerLiteral|HexLiteral|RealLiteral
    {
        $text = str_replace('_', '', $token->text);
        if (preg_match('/\A0[xX]([0-9A-Fa-f]+)\z/', $text, $hex) === 1) {
            return $this->lowering->leaves->record(new HexLiteral(strtoupper($hex[1])));
        }
        if (preg_match('/\A[0-9]+\z/', $text) === 1) {
            return $this->lowering->leaves->record(new IntegerLiteral($text));
        }
        $matched = preg_match('/\A([0-9]*)(?:\.([0-9]*))?(?:[eE]([+-]?[0-9]+))?\z/', $text, $parts, PREG_UNMATCHED_AS_NULL);
        Check::invariant($matched === 1, 'The numeric token has no numeric spelling: ' . $token->text);

        return $this->lowering->leaves->record(new RealLiteral($parts[1], $parts[2] ?? null, $parts[3] ?? null));
    }
}
