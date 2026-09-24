<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;

/**
 * A passwordless account: its plugin becomes the second factor and a temporary first-factor credential is supplied.
 * @visibility public
 * @example Reading the temporary credential form
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER u IDENTIFIED WITH authentication_fido INITIAL AUTHENTICATION IDENTIFIED BY RANDOM PASSWORD');
 *     [$statement->accounts[0]->plugin, $statement->accounts[0]->initialAuthentication] // => ['authentication_fido', \SqlSemantics\Model\Definition\Account\Identification\RandomPassword::Generated]
 */
final class InitialAuthenticationDefinition
{
    /**
     * Requires the passwordless plugin and the initial credential form.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly AccountName|CurrentAccount $account,
        public readonly string $plugin,
        public readonly PasswordIdentification|RandomPassword|PluginHashIdentification $initialAuthentication,
    ) {
        IdentificationOperands::plugin($plugin);
    }
}
