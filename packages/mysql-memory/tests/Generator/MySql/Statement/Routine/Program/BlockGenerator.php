<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Block values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Block>
 */
final class BlockGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Block> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Declaration>>|null $declarations Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement|\SqlSemantics\Platform\MySql\Statement\Statement>>|null $statements Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $label Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $endLabel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $declarations = null, ?ErisGenerator $statements = null, ?ErisGenerator $label = null, ?ErisGenerator $endLabel = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block(declarations: $parts[0], statements: $parts[1], label: $parts[2], endLabel: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $declarations ?? Generators::constant([]),
            $statements ?? Generators::constant([]),
            $label ?? Generators::constant(null),
            $endLabel ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
