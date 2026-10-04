<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

/**
 * An option of CREATE SERVER and ALTER SERVER.
 *
 * Mirrors the members of Server_options. PORT takes a number, the others a
 * string.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Socket->value // => 'SOCKET'
 */
enum ServerOptionKind: string
{
    case User = 'USER';
    case Host = 'HOST';
    case Database = 'DATABASE';
    case Owner = 'OWNER';
    case Password = 'PASSWORD';
    case Socket = 'SOCKET';
    case Port = 'PORT';
}
