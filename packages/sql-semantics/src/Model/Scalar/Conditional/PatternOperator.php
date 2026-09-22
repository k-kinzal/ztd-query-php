<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

/**
 * The pattern language used by a SQL pattern predicate.
 * @visibility public
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
