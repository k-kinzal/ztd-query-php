<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Binary values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Binary>
 */
final class BinaryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Binary> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $length Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $length = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Binary {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Binary(kind: $parts[0], length: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $kind,
            $length ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
