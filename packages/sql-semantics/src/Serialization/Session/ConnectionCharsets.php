<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Session;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;

/**
 * Writes the MySQL SET items NAMES and CHARACTER SET; CHARSET is spelled CHARACTER SET.
 * @visibility SqlSemantics
 */
final class ConnectionCharsets
{
    /**
     * Spells a missing character set as DEFAULT and adds COLLATE only when a collation is named.
     */
    public static function write(ConnectionNames|ConnectionCharacterSet $setting): Tree
    {
        $name = $setting->characterSet === null ? Build::keyword('DEFAULT') : Build::identifier([$setting->characterSet], Dialect::MySql);
        if ($setting instanceof ConnectionCharacterSet) {
            return new Tree('character-set-setting', [Build::keyword('CHARACTER SET'), $name]);
        }
        $collation = $setting->collation === null ? [] : [Build::keyword('COLLATE'), Build::identifier([$setting->collation], Dialect::MySql)];
        return new Tree('names-setting', [Build::keyword('NAMES'), $name, ...$collation]);
    }
}
