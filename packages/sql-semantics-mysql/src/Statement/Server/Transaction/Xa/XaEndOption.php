<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

/**
 * The option of XA END: SUSPEND or SUSPEND FOR MIGRATE.
 *
 * Mirrors XA_SUSPEND and XA_FOR_MIGRATE of xa_option_words. Each case holds
 * the keywords it is written with. The grammar accepts both; the server
 * answers ER_XAER_INVAL when it executes either.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEndOption::SuspendForMigrate->value // => 'SUSPEND FOR MIGRATE'
 */
enum XaEndOption: string
{
    case Suspend = 'SUSPEND';
    case SuspendForMigrate = 'SUSPEND FOR MIGRATE';
}
