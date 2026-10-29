<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IndexEngineAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute>
 */
final class IndexEngineAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $attribute Generated constructor operand
     * @param ErisGenerator<bool>|null $secondary Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $operator Generated constructor operand
     */
    public function __construct(ErisGenerator $attribute, ?ErisGenerator $secondary = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute(attribute: $parts[0], secondary: $parts[1], inputSource: $parts[2], operator: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $attribute,
            $secondary ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null),
            $operator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional()
        ));
    }
}
