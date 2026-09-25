<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

/**
 * How a database call is written in PHP.
 *
 * @visibility root
 */
enum SinkCallKind: string
{
    case Method = 'method';
    case StaticCall = 'static';
    case FunctionCall = 'function';
}
