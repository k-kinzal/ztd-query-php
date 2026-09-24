<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

/**
 * Sequence options that carry no value: cycling, unbounded limits, and persistence.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Identity\SequenceFlag::NoMaxValue->value // => 'NO MAXVALUE'
 */
enum SequenceFlag: string
{
    case Cycle = 'CYCLE';
    case NoCycle = 'NO CYCLE';
    case NoMinValue = 'NO MINVALUE';
    case NoMaxValue = 'NO MAXVALUE';
    case Logged = 'LOGGED';
    case Unlogged = 'UNLOGGED';
}
