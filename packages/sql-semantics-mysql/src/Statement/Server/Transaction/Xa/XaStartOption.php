<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

/**
 * The option of XA START: JOIN or RESUME.
 *
 * Mirrors XA_JOIN and XA_RESUME of xa_option_words. The grammar accepts both;
 * the server answers ER_XAER_INVAL when it executes either.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStartOption::Resume->value // => 'RESUME'
 */
enum XaStartOption: string
{
    case Join = 'JOIN';
    case Resume = 'RESUME';
}
