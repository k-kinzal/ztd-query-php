<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

/**
 * The two kinds of table members whose firing ALTER TABLE enables or disables.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\FiringTarget::Rule->value // => 'RULE'
 */
enum FiringTarget: string
{
    case Trigger = 'TRIGGER';
    case Rule = 'RULE';
}
