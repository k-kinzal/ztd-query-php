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
     */
    public function __construct(public readonly PieceKind $kind, public readonly string $text, public readonly bool $glued = false)
    {
    }
}
