<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

/**
 * Table properties that ALTER TABLE removes without any further operand.
 * @visibility public
 * @example Reading the SQL spelling of a removal
 *     \SqlSemantics\Model\Definition\Relation\RelationProperty::Cluster->value // => 'SET WITHOUT CLUSTER'
 */
enum RelationProperty: string
{
    case Oids = 'SET WITHOUT OIDS';
    case Cluster = 'SET WITHOUT CLUSTER';
    case Type = 'NOT OF';
}
