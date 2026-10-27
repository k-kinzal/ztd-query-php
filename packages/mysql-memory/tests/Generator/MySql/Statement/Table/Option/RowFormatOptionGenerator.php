<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RowFormatOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption>
 */
final class RowFormatOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat> $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $format, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption(format: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $format,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
