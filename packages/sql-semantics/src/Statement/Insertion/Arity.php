<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Insertion;

/**
 * Compatibility of inserted row widths with the target columns.
 * @visibility public
 * @example Identifying the missing fact behind an unresolved width
 *     \SqlSemantics\Statement\Insertion\Arity::MissingDeclaration->value // => 'missing-declaration'
 */
enum Arity: string
{
    case Matching = 'matching';
    case Mismatch = 'mismatch';
    case MissingTable = 'missing-table';
    case MissingDeclaration = 'missing-declaration';
    case ConflictingDeclarations = 'conflicting-declarations';
}
