<?php

declare(strict_types=1);

namespace SqlSemantics\Rendering;

/**
 * Joins output pieces into SQL text with a fixed, minimal spacing rule.
 *
 * Pieces are separated by one space, except around punctuation that never
 * merges with a neighbour and where a node asked for glue. The rule performs
 * no formatting and no normalization of the pieces themselves.
 *
 * @visibility SqlSemantics
 */
final class Lexical
{
    /**
     * Joins the pieces into text.
     *
     * @param list<Piece> $pieces
     */
    public function join(array $pieces): string
    {
        $sql = '';
        $previous = null;
        foreach ($pieces as $piece) {
            if ($previous !== null && !$this->tight($previous, $piece)) {
                $sql .= ' ';
            }
            $sql .= $piece->text;
            $previous = $piece;
        }

        return $sql;
    }

    /**
     * Decides whether two neighbouring pieces are written without a space.
     */
    public function tight(Piece $left, Piece $right): bool
    {
        if ($right->glued) {
            return true;
        }
        if ($right->kind === PieceKind::Symbol && in_array($right->text, [',', ')', ']', ';', '.'], true)) {
            return true;
        }

        return $left->kind === PieceKind::Symbol && in_array($left->text, ['(', '[', '.'], true);
    }
}
