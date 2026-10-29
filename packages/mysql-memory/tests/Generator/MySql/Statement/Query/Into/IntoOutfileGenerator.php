<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Into;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IntoOutfile values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile>
 */
final class IntoOutfileGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $file Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\FileFormat> $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $file, ErisGenerator $format, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile(file: $parts[0], format: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $file,
            $format,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
