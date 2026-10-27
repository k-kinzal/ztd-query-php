<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Form;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ResourceGroupHint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint>
 */
final class ResourceGroupHintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint> */
    use Composition;

    /**
     * @param ErisGenerator<string> $group Generated constructor operand
     */
    public function __construct(ErisGenerator $group)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint(group: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $group
        ));
    }
}
