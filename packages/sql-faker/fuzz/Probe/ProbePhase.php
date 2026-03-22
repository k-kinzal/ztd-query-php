<?php

declare(strict_types=1);

namespace Fuzz\Probe;

/**
 * Identifies whether a statement was accepted during prepare, during execute,
 * or before the probe reached the database engine at all.
 */
enum ProbePhase: string
{
    case None = 'none';
    case Prepare = 'prepare';
    case Execute = 'execute';
}
