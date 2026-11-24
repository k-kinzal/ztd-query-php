<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header;

/**
 * A writable SQLite file header field.
 *
 * @visibility public
 * @example Selecting the application identifier cookie
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderCookie::ApplicationId->value // => 'application_id'
 */
enum HeaderCookie: string
{
    case ApplicationId = 'application_id';
    case UserVersion = 'user_version';
    case SchemaVersion = 'schema_version';
}
