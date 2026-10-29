<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PrivilegeChange values and preserves their operand generators when shrinking.
 *
 * @template T of \SqlSemantics\Platform\MySql\Statement\Account\Privilege\PrivilegeChange
 * @implements ErisGenerator<T>
 */
final class PrivilegeChangeGenerator implements ErisGenerator
{
    /** @use Composition<T> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<T>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Generators::oneOf(...$alternatives);
    }
}
