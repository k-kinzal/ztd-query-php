<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InformationItem values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem>
 */
final class InformationItemGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable> $target Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName|\SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName> $item Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $target, ErisGenerator $item, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem(target: $parts[0], item: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $target,
            $item,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
