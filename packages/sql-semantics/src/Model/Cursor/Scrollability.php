<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A classified cursor scrollability choice.
 * @visibility public
 */
enum Scrollability: string
{
    case Default = '';
    case Scroll = 'SCROLL';
    case ForwardOnly = 'NO SCROLL';
}
