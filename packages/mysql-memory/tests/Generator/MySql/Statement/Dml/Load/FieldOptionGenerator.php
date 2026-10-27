<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FieldOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption>
 */
final class FieldOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $text Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $text, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption(kind: $parts[0], text: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $text,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
