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
    /**
     * The client uses the improved password hashing (CLIENT_LONG_PASSWORD).
     */
    public const LONG_PASSWORD = 1;

    /**
     * The affected rows of an UPDATE count the rows found rather than the rows changed (CLIENT_FOUND_ROWS).
     */
    public const FOUND_ROWS = 2;

    /**
     * The column definitions carry the long column flags (CLIENT_LONG_FLAG).
     */
    public const LONG_FLAG = 4;

    /**
     * The handshake response may name the database to use (CLIENT_CONNECT_WITH_DB).
     */
    public const CONNECT_WITH_DB = 8;

    /**
     * The 4.1 protocol is used, with its column definitions and SQLSTATE in errors (CLIENT_PROTOCOL_41).
     */
    public const PROTOCOL_41 = 512;

    /**
     * The connection may switch to TLS after the handshake (CLIENT_SSL).
     */
    public const SSL = 2048;

    /**
     * The status flags of OK and EOF packets report transactions (CLIENT_TRANSACTIONS).
     */
    public const TRANSACTIONS = 8192;

    /**
     * The authentication data is sent with its length (CLIENT_SECURE_CONNECTION).
     */
    public const SECURE_CONNECTION = 32768;

    /**
     * A COM_QUERY may hold several statements (CLIENT_MULTI_STATEMENTS).
     */
    public const MULTI_STATEMENTS = 65536;

    /**
     * A COM_QUERY may answer several results (CLIENT_MULTI_RESULTS).
     */
    public const MULTI_RESULTS = 131072;

    /**
     * A COM_STMT_EXECUTE may answer several results (CLIENT_PS_MULTI_RESULTS).
     */
    public const PS_MULTI_RESULTS = 262144;

    /**
     * The handshake names the authentication plugin (CLIENT_PLUGIN_AUTH).
     */
    public const PLUGIN_AUTH = 524288;

    /**
     * The handshake response may carry connection attributes (CLIENT_CONNECT_ATTRS).
     */
    public const CONNECT_ATTRS = 1048576;

    /**
     * The authentication data of the handshake response is a length-encoded string (CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA).
     */
    public const PLUGIN_AUTH_LENENC_CLIENT_DATA = 2097152;

    /**
     * An OK packet replaces the EOF packets of a result set (CLIENT_DEPRECATE_EOF).
     */
    public const DEPRECATE_EOF = 16777216;
}
