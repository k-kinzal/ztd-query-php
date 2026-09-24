<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

/**
 * The search modifier of a MySQL full-text search, spelled as its SQL clause.
 * @visibility public
 * @example Reading the clause of a Boolean-mode search
 *     \SqlSemantics\Model\Scalar\Text\FullTextMode::Boolean->value // => 'IN BOOLEAN MODE'
 */
enum FullTextMode: string
{
    case NaturalLanguage = 'IN NATURAL LANGUAGE MODE';
    case QueryExpansion = 'WITH QUERY EXPANSION';
    case Boolean = 'IN BOOLEAN MODE';
}
