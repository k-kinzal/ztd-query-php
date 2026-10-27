<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Item;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Privilege values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege>
 */
final class PrivilegeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = new GrantableGenerator($alternatives);
    }
}
