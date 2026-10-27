<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes JsonResponse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse>
 */
final class JsonResponseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal|null>|null $default Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $default = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse(kind: $parts[0], default: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $default ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
