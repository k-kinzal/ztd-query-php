<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

/**
 * What a REQUIRE clause requires of an account's connections: nothing, TLS, a valid X.509 certificate, or the listed certificate and cipher properties.
 *
 * Mirrors SSL_type (SSL_TYPE_NONE, SSL_TYPE_ANY, SSL_TYPE_X509,
 * SSL_TYPE_SPECIFIED). `REQUIRE NONE` is a request of its own: ALTER USER
 * with it removes an earlier requirement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-tls.
 *
 * @visibility public
 * @example Naming the X.509 requirement
 *     \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind::X509->name // => 'X509'
 */
enum TlsKind
{
    case None;
    case Ssl;
    case X509;
    case Specified;
}
