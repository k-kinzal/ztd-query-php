<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

/**
 * The multifactor position an ALTER USER request addresses; the first factor has no number.
 * @visibility public
 * @example Inspecting a factor position
 *     \SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor::Third->value // => '3'
 */
enum AuthenticationFactor: string
{
    case Second = '2';
    case Third = '3';
}
