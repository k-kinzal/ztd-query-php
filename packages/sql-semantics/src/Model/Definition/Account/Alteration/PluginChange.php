<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;

/**
 * Switches the first-factor plugin without supplying a credential; nothing can be retained.
 * @visibility public
 * @example Reading the selected plugin
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER u IDENTIFIED WITH caching_sha2_password');
 *     $statement->alterations[0]->plugin // => 'caching_sha2_password'
 */
final class PluginChange
{
    /**
     * Requires a nonempty plugin name.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly AccountName|CurrentAccount $account, public readonly string $plugin)
    {
        IdentificationOperands::plugin($plugin);
    }
}
