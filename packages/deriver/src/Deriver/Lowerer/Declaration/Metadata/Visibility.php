<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration\Metadata;

/**
 * PHP access levels for methods, properties, and class constants.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Public->value // => 'public'
 * @visibility root
 */
enum Visibility: string
{
    case Public = 'public';
    case Protected = 'protected';
    case Private = 'private';
}
