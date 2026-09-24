<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

/**
 * The numeric attributes of an identity column's sequence.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Identity\SequenceAttribute::Increment->value // => 'INCREMENT BY'
 */
enum SequenceAttribute: string
{
    case Increment = 'INCREMENT BY';
    case MinValue = 'MINVALUE';
    case MaxValue = 'MAXVALUE';
    case Start = 'START WITH';
    case Cache = 'CACHE';
}
