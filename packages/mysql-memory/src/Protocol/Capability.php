<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

/**
 * The capability flags client and server exchange in the handshake.
 *
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__capabilities__flags.html.
 *
 * @visibility MySqlMemory
 */
final class Capability
{
    public const LONG_PASSWORD = 1;
    public const FOUND_ROWS = 2;
    public const LONG_FLAG = 4;
    public const CONNECT_WITH_DB = 8;
    public const PROTOCOL_41 = 512;
    public const SSL = 2048;
    public const TRANSACTIONS = 8192;
    public const SECURE_CONNECTION = 32768;
    public const MULTI_STATEMENTS = 65536;
    public const MULTI_RESULTS = 131072;
    public const PS_MULTI_RESULTS = 262144;
    public const PLUGIN_AUTH = 524288;
    public const CONNECT_ATTRS = 1048576;
    public const PLUGIN_AUTH_LENENC_CLIENT_DATA = 2097152;
    public const DEPRECATE_EOF = 16777216;
}
