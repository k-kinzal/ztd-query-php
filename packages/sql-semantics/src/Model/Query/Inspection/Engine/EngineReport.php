<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Engine;

/**
 * The report a storage engine is asked to produce.
 * @visibility public
 * @example Inspecting a requested engine report
 *     \SqlSemantics\Model\Query\Inspection\Engine\EngineReport::Mutex->value // => 'MUTEX'
 */
enum EngineReport: string
{
    case Logs = 'LOGS';
    case Mutex = 'MUTEX';
    case Status = 'STATUS';
}
