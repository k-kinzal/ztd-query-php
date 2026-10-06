<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

/**
 * A certificate or cipher property a `REQUIRE SUBJECT|ISSUER|CIPHER '…'` clause demands.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-tls.
 *
 * @visibility public
 * @example Reading the keyword of a property
 *     \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute::Issuer->value // => 'ISSUER'
 */
enum TlsAttribute: string
{
    case Subject = 'SUBJECT';
    case Issuer = 'ISSUER';
    case Cipher = 'CIPHER';
}
