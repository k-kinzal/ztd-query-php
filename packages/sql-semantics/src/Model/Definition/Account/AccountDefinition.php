<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One account being created or granted: its optional first-factor identification and up to two further factors.
 * @visibility public
 * @example Reading a multifactor definition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE USER u IDENTIFIED BY 'x' AND IDENTIFIED WITH authentication_fido");
 *     [$statement->accounts[0]->identification::class, count($statement->accounts[0]->additionalFactors)] // => [\SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification::class, 1]
 * @example Rejecting more than two additional factors
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE USER u IDENTIFIED WITH p');
 *     $factor = $statement->accounts[0]->identification;
 *     new \SqlSemantics\Model\Definition\Account\AccountDefinition($statement->accounts[0]->account, $factor, [$factor, $factor, $factor]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AccountDefinition
{
    /**
     * @param list<PasswordIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification> $additionalFactors Second and third factors in order
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly AccountName|CurrentAccount $account,
        public readonly PasswordIdentification|HashIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification|null $identification = null,
        public readonly array $additionalFactors = [],
    ) {
        Collections::alternatives($additionalFactors, [PasswordIdentification::class, RandomPassword::class, PluginIdentification::class, PluginHashIdentification::class, PluginPasswordIdentification::class, PluginRandomPasswordIdentification::class]);
        if (count($additionalFactors) > 2) {
            throw new InvalidStructure('An account accepts at most a second and a third authentication factor.');
        }
    }
}
