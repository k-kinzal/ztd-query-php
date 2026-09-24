<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

/**
 * The pattern language used by a SQL pattern predicate.
 * @visibility public
 * @example Reading the operator spelling
 *     \SqlSemantics\Model\Scalar\Conditional\PatternOperator::SimilarTo->value // => 'SIMILAR TO'
 */
enum PatternOperator: string
{
    case Like = 'LIKE';
    case ILike = 'ILIKE';
    case Glob = 'GLOB';
    case Regexp = 'REGEXP';
    case Match = 'MATCH';
    case SimilarTo = 'SIMILAR TO';
}
