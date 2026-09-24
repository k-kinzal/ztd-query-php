<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

/**
 * The four row-level security switches of a table.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\RowSecurityChange::NoForce->value // => 'NO FORCE ROW LEVEL SECURITY'
 */
enum RowSecurityChange: string
{
    case Enable = 'ENABLE ROW LEVEL SECURITY';
    case Disable = 'DISABLE ROW LEVEL SECURITY';
    case Force = 'FORCE ROW LEVEL SECURITY';
    case NoForce = 'NO FORCE ROW LEVEL SECURITY';
}
