<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Characteristic;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Characteristic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic>
 */
final class CharacteristicGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
