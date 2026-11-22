<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

/**
 * The kinds of schema objects a DROP statement removes.
 *
 * Source: https://sqlite.org/lang_droptable.html, https://sqlite.org/lang_dropview.html,
 * https://sqlite.org/lang_dropindex.html, https://sqlite.org/lang_droptrigger.html.
 *
 * @visibility SqlSemantics
 */
enum SchemaObjectKind: string
{
    case Table = 'TABLE';
    case View = 'VIEW';
    case Index = 'INDEX';
    case Trigger = 'TRIGGER';
}
