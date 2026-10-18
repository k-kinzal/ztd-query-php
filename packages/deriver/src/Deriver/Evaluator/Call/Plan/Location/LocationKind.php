<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan\Location;

/**
 * The storage addressed by a declarative evaluator location.
 * @visibility root
 */
enum LocationKind: string
{
    case Parameter = 'parameter';
    case State = 'state';
    case Element = 'element';
}
