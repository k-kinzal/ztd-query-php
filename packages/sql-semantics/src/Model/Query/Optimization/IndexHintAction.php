<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

/**
 * What a MySQL index hint asks the optimizer to do with the named indexes, backed by its keyword.
 *
 * @visibility public
 * @example Reading the action of an index hint
 *     \SqlSemantics\Model\Query\Optimization\IndexHintAction::from('FORCE') // => \SqlSemantics\Model\Query\Optimization\IndexHintAction::Force
 */
enum IndexHintAction: string
{
    case Use = 'USE';
    case Ignore = 'IGNORE';
    case Force = 'FORCE';
}
