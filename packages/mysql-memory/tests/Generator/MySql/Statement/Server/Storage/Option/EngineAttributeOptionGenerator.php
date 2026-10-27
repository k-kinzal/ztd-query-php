<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EngineAttributeOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption>
 */
final class EngineAttributeOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $attribute Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $attribute, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption(attribute: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $attribute,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
