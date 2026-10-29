<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Into;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IntoDumpfile values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile>
 */
final class IntoDumpfileGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $file Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $file, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile(file: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $file,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
