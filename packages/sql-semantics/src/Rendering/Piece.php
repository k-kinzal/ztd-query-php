<?php

declare(strict_types=1);

namespace SqlSemantics\Rendering;

/**
 * One lexical unit of rendered SQL.
 *
 * @visibility SqlSemantics
 */
final class Piece
{
    /**
     * @param PieceKind $kind What the piece is
     * @param string $text The exact spelling
     * @param bool $glued Whether the piece follows the previous one without a separating space
     * @param string|null $gap The exact trivia written before the piece, from a layout; null for the regular spacing
     */
    public function __construct(public readonly PieceKind $kind, public readonly string $text, public readonly bool $glued = false, public readonly ?string $gap = null)
    {
    }
}
