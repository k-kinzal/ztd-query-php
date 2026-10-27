<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OrdinalityColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn>
 */
final class OrdinalityColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(name: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
