<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

/**
 * Which of the two rows an UPSERT assignment reads a column from.
 *
 * A conflicting write has two rows in play: the one already there and the one
 * the statement was trying to write. Every dialect spells the difference
 * differently and this is what they all mean.
 */
enum UpsertColumnSource
{
    case Existing;
    case Incoming;
}
