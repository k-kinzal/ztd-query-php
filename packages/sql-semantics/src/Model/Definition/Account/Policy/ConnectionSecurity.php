<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Policy;

/**
 * REQUIRE NONE, SSL, or X509: the transport security an account must present.
 * @visibility public
 * @example Inspecting a connection requirement
 *     \SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity::X509->value // => 'X509'
 */
enum ConnectionSecurity: string
{
    case None = 'NONE';
    case Ssl = 'SSL';
    case X509 = 'X509';
}
