<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes JsonValueCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall>
 */
final class JsonValueCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $document Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral> $path Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget|null>|null $returning Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse|null>|null $onEmpty Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse|null>|null $onError Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\Path|\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid|null>|null $pathValue Generated selection steps
     */
    public function __construct(ErisGenerator $document, ErisGenerator $path, ?ErisGenerator $returning = null, ?ErisGenerator $onEmpty = null, ?ErisGenerator $onError = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $pathValue = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(document: $parts[0], path: $parts[1], returning: $parts[2], onEmpty: $parts[3], onError: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6], pathValue: $parts[7]);
        }, Generators::tuple(
            $document,
            $path,
            $returning ?? Generators::constant(null),
            $onEmpty ?? Generators::constant(null),
            $onError ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $pathValue ?? Generators::constant(null)
        ));
    }
}
