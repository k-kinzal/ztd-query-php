<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription;

/**
 * Whether ALTER SUBSCRIPTION sets parameters or skips a remote transaction.
 *
 * Source: https://www.postgresql.org/docs/17/sql-altersubscription.html.
 *
 * @visibility public
 * @example Spelling the skipping action
 *     \SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription\SubscriptionOptionAction::Skip->value // => 'SKIP'
 */
enum SubscriptionOptionAction: string
{
    case Set = 'SET';
    case Skip = 'SKIP';
}
