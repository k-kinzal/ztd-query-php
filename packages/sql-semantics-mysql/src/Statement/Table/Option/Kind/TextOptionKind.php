<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option\Kind;

/**
 * The table options whose value is a string.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind::DataDirectory->value // => 'DATA DIRECTORY'
 */
enum TextOptionKind: string
{
    case Password = 'PASSWORD';
    case Comment = 'COMMENT';
    case DataDirectory = 'DATA DIRECTORY';
    case IndexDirectory = 'INDEX DIRECTORY';
    case Connection = 'CONNECTION';
    case Compression = 'COMPRESSION';
    case Encryption = 'ENCRYPTION';
    case EngineAttribute = 'ENGINE_ATTRIBUTE';
    case SecondaryEngineAttribute = 'SECONDARY_ENGINE_ATTRIBUTE';
}
