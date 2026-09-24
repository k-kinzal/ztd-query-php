<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * IDENTIFIED WITH plugin AS 'authentication string': a plugin and its already encoded credential.
 * @visibility public
 * @example Reading the plugin and authentication string
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u IDENTIFIED WITH mysql_native_password AS '*hash'");
 *     [$statement->accounts[0]->identification->plugin, $statement->accounts[0]->identification->hash->text] // => ['mysql_native_password', "'*hash'"]
 */
final class PluginHashIdentification
{
    /**
     * Requires both the plugin name and the encoded credential literal.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly string $plugin, public readonly Literal $hash)
    {
        IdentificationOperands::plugin($plugin);
        IdentificationOperands::hash($hash);
    }
}
