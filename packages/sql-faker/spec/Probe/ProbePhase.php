<?php

declare(strict_types=1);

namespace Spec\Probe;

/**
 * Identifies whether a witness was accepted during prepare, during execute, or
 * before it reached the engine at all.
 */
enum ProbePhase: string
{
    case None = 'none';
    case Prepare = 'prepare';
    case Execute = 'execute';
}
