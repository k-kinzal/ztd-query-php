<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Decodes the written options into their finite native request types.
 *
 * @visibility SqlSemantics
 */
final class IndexPolicyLowering
{
    /**
     * Resolves native algorithm and lock options.
     * @param list<AlterOption> $options The written alteration options
     */
    public function lower(array $options): IndexChangePolicy
    {
        $algorithm = IndexAlgorithm::Default;
        $lock = IndexLock::Default;
        foreach ($options as $option) {
            if ($option instanceof AlgorithmOption) {
                $requested = IndexAlgorithm::tryFrom(strtr($option->algorithm->value ?? 'DEFAULT', 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
                Check::input($requested instanceof IndexAlgorithm, 'Ordinary InnoDB index changes accept DEFAULT, INPLACE or COPY algorithms.');
                $algorithm = $requested;
            } else {
                Check::input($option instanceof LockOption, 'An ordinary InnoDB index change requires a checked native lock.');
                $requested = IndexLock::tryFrom(strtr($option->lock->value ?? 'DEFAULT', 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
                Check::input($requested instanceof IndexLock, 'An ordinary InnoDB index change requires a checked native lock.');
                $lock = $requested;
            }
        }

        return new IndexChangePolicy($algorithm, $lock);
    }
}
