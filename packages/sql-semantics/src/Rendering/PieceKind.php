<?php

declare(strict_types=1);

namespace SqlSemantics\Rendering;

/**
 * The kinds of output pieces a node can write.
 *
 * @visibility SqlSemantics
 */
enum PieceKind
{
    case Keyword;
    case Symbol;
    case Name;
    case Literal;
}
