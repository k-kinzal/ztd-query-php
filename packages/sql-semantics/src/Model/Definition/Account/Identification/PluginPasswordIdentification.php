<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

use SqlSemantics\Model\Scalar\Value\Literal;

/**
 * IDENTIFIED WITH plugin BY 'password': a plugin and the cleartext credential it hashes.
 * @visibility public
 * @example Reading the plugin and credential
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u IDENTIFIED WITH caching_sha2_password BY 'secret'");
 *     $statement->accounts[0]->identification->password->text // => "'secret'"
 */
final class PluginPasswordIdentification
{
    /**
     * Requires both the plugin name and the cleartext credential literal.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly string $plugin, public readonly Literal $password)
    {
        IdentificationOperands::plugin($plugin);
        IdentificationOperands::secret($password);
    }
}
