<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * DROP one or two distinct numbered factors.
 * @visibility public
 * @example Reading the dropped factors
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER u DROP 3 FACTOR DROP 2 FACTOR');
 *     array_column($statement->alterations[0]->factors, 'value') // => ['3', '2']
 * @example Rejecting the same factor twice
 *     new \SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval(new \SqlSemantics\Model\Configuration\Account\AccountName('u'), [\SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor::Second, \SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor::Second]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class FactorRemoval
{
    /**
     * @param non-empty-list<AuthenticationFactor> $factors One or two distinct factors in request order
     * @throws InvalidStructure
     */
    public function __construct(public readonly AccountName|CurrentAccount $account, public readonly array $factors)
    {
        Collections::alternatives(Collections::nonEmpty($factors), [AuthenticationFactor::class]);
        if (count($factors) > 2 || count(array_unique(array_column($factors, 'value'))) !== count($factors)) {
            throw new InvalidStructure('A factor removal drops one or two distinct factors.');
        }
    }
}
