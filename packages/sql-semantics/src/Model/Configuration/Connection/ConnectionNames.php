<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Connection;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SET NAMES: sets the client, connection and result character sets together, with the connection collation.
 * A null character set is NAMES DEFAULT, which restores the server's default client character set.
 * @visibility public
 * @example Reading the requested character set and collation
 *     $setting = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SET NAMES utf8mb4 COLLATE utf8mb4_bin')->settings[0];
 *     [$setting->characterSet, $setting->collation] // => ['utf8mb4', 'utf8mb4_bin']
 */
final class ConnectionNames
{
    /**
     * @param string|null $characterSet Character set name; null restores the default
     * @param string|null $collation Connection collation; null uses the character set's default collation
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $characterSet = null, public readonly ?string $collation = null)
    {
        if ($characterSet === '' || $collation === '') {
            throw new InvalidStructure('SET NAMES requires nonempty character set and collation names.');
        }
    }
}
