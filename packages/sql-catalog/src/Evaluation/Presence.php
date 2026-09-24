<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

/**
 * Whether a variable exists, independently of its value domain.
 *
 * @visibility root
 */
enum Presence: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Maybe = 'maybe';
}
