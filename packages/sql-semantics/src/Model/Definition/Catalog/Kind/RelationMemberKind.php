<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * Object classes that are addressed by their own name together with the relation that owns them.
 * @visibility public
 * @example Reading the SQL spelling of a relation member class
 *     \SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind::Policy->value // => 'POLICY'
 */
enum RelationMemberKind: string
{
    case Column = 'COLUMN';
    case Constraint = 'CONSTRAINT';
    case Policy = 'POLICY';
    case Rule = 'RULE';
    case Trigger = 'TRIGGER';
}
