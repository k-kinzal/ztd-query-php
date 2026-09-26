<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Schema;

/**
 * The integrity condition a fixture generator must respect.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Core\Schema\ConstraintKind::ForeignKey->value // => 'foreign-key'
 *
 * @visibility public
 */
enum ConstraintKind: string
{
    case PrimaryKey = 'primary-key';
    case Unique = 'unique';
    case ForeignKey = 'foreign-key';
    case Check = 'check';
}
