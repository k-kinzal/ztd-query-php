<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;

/**
 * Replaces the first-factor credential with an encoded or generated value; no replaced password can be verified.
 * @visibility public
 * @example Reading a retained plugin credential change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER u IDENTIFIED WITH p AS 'hash' RETAIN CURRENT PASSWORD");
 *     $statement->alterations[0]->retainCurrentPassword // => true
 */
final class AuthenticationChange
{
    /**
     * Pairs the account with its encoded or generated identification and the retention flag.
     */
    public function __construct(
        public readonly AccountName|CurrentAccount $account,
        public readonly PluginHashIdentification|PluginRandomPasswordIdentification|HashIdentification $identification,
        public readonly bool $retainCurrentPassword = false,
    ) {
    }
}
