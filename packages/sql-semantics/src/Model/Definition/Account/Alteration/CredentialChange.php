<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Supplies or generates a new first-factor credential, optionally verifying the replaced one and retaining it as secondary.
 * @visibility public
 * @example Reading a replaced credential
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER u IDENTIFIED BY 'new' REPLACE 'old' RETAIN CURRENT PASSWORD");
 *     [$statement->alterations[0]->replacedPassword->text, $statement->alterations[0]->retainCurrentPassword] // => ["'old'", true]
 * @example Rejecting a plugin credential for the connecting client account
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER u IDENTIFIED WITH p BY 'x'");
 *     new \SqlSemantics\Model\Definition\Account\Alteration\CredentialChange(\SqlSemantics\Model\Configuration\Account\ClientAccount::Connected, $statement->alterations[0]->identification); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CredentialChange
{
    /**
     * The connecting client account accepts only plugin-less credential forms.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly AccountName|CurrentAccount|ClientAccount $account,
        public readonly PasswordIdentification|RandomPassword|PluginPasswordIdentification $identification,
        public readonly ?Literal $replacedPassword = null,
        public readonly bool $retainCurrentPassword = false,
    ) {
        if ($replacedPassword !== null) {
            IdentificationOperands::secret($replacedPassword);
        }
        if ($account instanceof ClientAccount && $identification instanceof PluginPasswordIdentification) {
            throw new InvalidStructure('The connecting client account cannot select an authentication plugin.');
        }
    }
}
