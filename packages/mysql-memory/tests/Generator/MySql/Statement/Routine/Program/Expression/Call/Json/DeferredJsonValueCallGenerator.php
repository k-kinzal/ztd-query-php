<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredJsonValueCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCall>
 */
final class DeferredJsonValueCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $document Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral> $path Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CastTarget|null>|null $returning Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse|null>|null $onEmpty Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse|null>|null $onError Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $document, ErisGenerator $path, ?ErisGenerator $returning = null, ?ErisGenerator $onEmpty = null, ?ErisGenerator $onError = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCall {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCall(document: $parts[0], path: $parts[1], returning: $parts[2], onEmpty: $parts[3], onError: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]);
        }, Generators::tuple(
            $document,
            $path,
            $returning ?? Generators::constant(null),
            $onEmpty ?? Generators::constant(null),
            $onError ?? Generators::constant(null),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
