<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * The syntactic position a name is spelled for; reserved words differ between positions.
 *
 * `Identifier` is a position that reads a plain identifier token only: every
 * keyword must be quoted there.
 *
 * @visibility public
 * @example Naming the position of a relation name
 *     \SqlSemantics\Contract\NameUse::Relation->name // => 'Relation'
 */
enum NameUse
{
    case Column;
    case Relation;
    case Qualifier;
    case Alias;
    case Routine;
    case Label;
    case Identifier;
}
