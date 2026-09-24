<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

/**
 * IDENTIFIED BY RANDOM PASSWORD: the server generates the credential; none is supplied.
 * @visibility public
 * @example Inspecting the generated-credential request
 *     \SqlSemantics\Model\Definition\Account\Identification\RandomPassword::Generated->value // => 'RANDOM PASSWORD'
 */
enum RandomPassword: string
{
    case Generated = 'RANDOM PASSWORD';
}
