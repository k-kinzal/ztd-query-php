<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Control;

/**
 * Lexical control scopes that determine whether a goto is legal.
 * @visibility root
 */
enum ScopeKind: string
{
    case Loop = 'loop';
    case Switch = 'switch';
    case Try = 'try';
    case Catch = 'catch';
    case Finally = 'finally';
}
