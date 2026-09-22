<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

/**
 * The selected SerializationCost instruction.
 * @visibility public
 */
enum SerializationCost: string
{
    case None = 'NONE';
    case Text = 'TEXT';
    case Binary = 'BINARY';
}
