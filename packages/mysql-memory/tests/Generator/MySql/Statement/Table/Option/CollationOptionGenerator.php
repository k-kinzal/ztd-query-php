<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CollationOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption>
 */
final class CollationOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName> $collation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $operator Generated constructor operand
     * @param ErisGenerator<bool>|null $defaultKeyword Generated constructor operand
     */
    public function __construct(ErisGenerator $collation, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null, ?ErisGenerator $defaultKeyword = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption(collation: $parts[0], inputSource: $parts[1], operator: $parts[2], defaultKeyword: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $collation,
            $inputSource ?? Generators::constant(null),
            $operator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional(),
            $defaultKeyword ?? Generators::bool()
        ));
    }
}
