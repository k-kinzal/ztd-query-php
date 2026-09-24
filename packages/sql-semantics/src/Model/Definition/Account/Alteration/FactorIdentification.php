<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;

/**
 * One numbered factor and the identification requested for it.
 * @visibility public
 * @example Reading the factor position of an added identification
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER u ADD 2 FACTOR IDENTIFIED WITH authentication_fido');
 *     $statement->alterations[0]->factors[0]->factor->value // => '2'
 */
final class FactorIdentification
{
    /**
     * Pairs the factor position with any of the identification forms MySQL accepts for a factor.
     */
    public function __construct(
        public readonly AuthenticationFactor $factor,
        public readonly PasswordIdentification|RandomPassword|PluginIdentification|PluginHashIdentification|PluginPasswordIdentification|PluginRandomPasswordIdentification $identification,
    ) {
    }
}
