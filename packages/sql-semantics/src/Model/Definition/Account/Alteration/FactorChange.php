<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account\Alteration;

use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * ADD or MODIFY one or two numbered factors; factors differ and additions ascend.
 * @visibility public
 * @example Reading an addition of two factors
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER u ADD 2 FACTOR IDENTIFIED WITH p ADD 3 FACTOR IDENTIFIED BY 'x'");
 *     [$statement->alterations[0]->operation->value, count($statement->alterations[0]->factors)] // => ['ADD', 2]
 * @example Rejecting the same factor twice
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER USER u MODIFY 2 FACTOR IDENTIFIED WITH p');
 *     $factor = $statement->alterations[0]->factors[0];
 *     new \SqlSemantics\Model\Definition\Account\Alteration\FactorChange($statement->alterations[0]->account, \SqlSemantics\Model\Definition\Account\Alteration\FactorOperation::Modify, [$factor, $factor]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class FactorChange
{
    /**
     * @param non-empty-list<FactorIdentification> $factors One or two distinct factors; ADD requires the second before the third
     * @throws InvalidStructure
     */
    public function __construct(public readonly AccountName|CurrentAccount $account, public readonly FactorOperation $operation, public readonly array $factors)
    {
        Collections::objects(Collections::nonEmpty($factors), FactorIdentification::class);
        if (count($factors) > 2) {
            throw new InvalidStructure('A factor change addresses at most two factors.');
        }
        if (count($factors) === 2 && $factors[0]->factor === $factors[1]->factor) {
            throw new InvalidStructure('A factor change cannot address the same factor twice.');
        }
        if ($operation === FactorOperation::Add && count($factors) === 2 && $factors[0]->factor === AuthenticationFactor::Third) {
            throw new InvalidStructure('Added factors must be listed in ascending order.');
        }
    }
}
