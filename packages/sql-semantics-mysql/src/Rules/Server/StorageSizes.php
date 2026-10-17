<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The intrinsic numeric domain of a storage size, before a statement is built.
 *
 * Integer tokens are unsigned 64-bit values. Unit words use K, M or G with a
 * coefficient below 2^31. These input checks precede option-list conflicts and
 * apply even when later syntax is invalid. Observed on MySQL 5.6 through 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StorageSizes
{
    /**
     * Describes an operand outside the size domain; the caller checks exclusivity.
     */
    public function problem(?Numeral $number, ?Name $word): FractionalNumber|StorageProblem|null
    {
        if ($number !== null) {
            return (new Magnitudes())->integral($number) ? null : new FractionalNumber($number);
        }
        if ($word === null || preg_match('/\A([0-9]*)[KMGkmg]\z/', $word->value, $match) !== 1) {
            return new StorageProblem(StorageRule::WrongSize, 'SIZE');
        }
        $digits = ltrim($match[1], '0');

        return strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, '2147483648') >= 0)
            ? new StorageProblem(StorageRule::SizeOverflow, 'SIZE')
            : null;
    }
}
