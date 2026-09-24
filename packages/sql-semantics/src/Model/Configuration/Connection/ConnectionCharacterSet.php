<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Connection;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SET CHARACTER SET (or CHARSET): sets the client and result character sets, and the connection character set to the database's.
 * A null character set is CHARACTER SET DEFAULT, which restores the server's default client character set.
 * @visibility public
 * @example Reading the requested character set
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build());
 *     [$binder->bind('SET CHARSET latin1')->settings[0]->characterSet, $binder->bind('SET CHARACTER SET DEFAULT')->settings[0]->characterSet] // => ['latin1', null]
 */
final class ConnectionCharacterSet
{
    /**
     * @param string|null $characterSet Character set name; null restores the default
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $characterSet = null)
    {
        if ($characterSet === '') {
            throw new InvalidStructure('SET CHARACTER SET requires a nonempty character set name.');
        }
    }
}
