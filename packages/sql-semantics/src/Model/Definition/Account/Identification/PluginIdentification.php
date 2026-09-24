<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Identification;

/**
 * IDENTIFIED WITH plugin: an authentication plugin without any credential.
 * @visibility public
 * @example Reading the plugin name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER u IDENTIFIED WITH caching_sha2_password');
 *     $statement->accounts[0]->identification->plugin // => 'caching_sha2_password'
 */
final class PluginIdentification
{
    /**
     * Requires the plugin name; the plugin itself is not resolved.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly string $plugin)
    {
        IdentificationOperands::plugin($plugin);
    }
}
