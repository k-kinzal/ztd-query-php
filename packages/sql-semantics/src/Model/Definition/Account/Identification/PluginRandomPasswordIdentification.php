<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

/**
 * IDENTIFIED WITH plugin BY RANDOM PASSWORD: a plugin whose credential the server generates.
 * @visibility public
 * @example Reading the plugin name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER u IDENTIFIED WITH caching_sha2_password BY RANDOM PASSWORD');
 *     $statement->accounts[0]->identification->plugin // => 'caching_sha2_password'
 */
final class PluginRandomPasswordIdentification
{
    /**
     * Requires the plugin name; no credential is supplied.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly string $plugin)
    {
        IdentificationOperands::plugin($plugin);
    }
}
