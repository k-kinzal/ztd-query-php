<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

/**
 * What a handler does after its statement: continue after the failing statement or exit the declaring block.
 * @visibility public
 * @example Reading a handler action
 *     \SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerAction::Exit->value // => 'EXIT'
 */
enum HandlerAction: string
{
    case Continue = 'CONTINUE';
    case Exit = 'EXIT';
}
