<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AddConstraint values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint>
 */
final class AddConstraintGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition|\SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey|\SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint> $element Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $element, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint(element: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $element,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
