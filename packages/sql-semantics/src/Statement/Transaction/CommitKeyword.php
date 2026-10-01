<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

/**
 * Equivalent spellings of a request to commit the transaction.
 * @example Describing the operation
 *     $keyword = \SqlSemantics\Statement\Transaction\CommitKeyword::End;
 *     $keyword->value // => 'END'
 * @visibility public
 */
enum CommitKeyword: string
{
    case Commit = 'COMMIT';
    case End = 'END';
}
