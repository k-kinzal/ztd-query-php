<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PathColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn>
 */
final class PathColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral> $path Generated constructor operand
     * @param ErisGenerator<bool>|null $exists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse|null>|null $onEmpty Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse|null>|null $onError Generated constructor operand
     * @param ErisGenerator<bool>|null $errorFirst Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $type, ErisGenerator $path, ?ErisGenerator $exists = null, ?ErisGenerator $collation = null, ?ErisGenerator $onEmpty = null, ?ErisGenerator $onError = null, ?ErisGenerator $errorFirst = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn(name: $parts[0], type: $parts[1], path: $parts[2], exists: $parts[3], collation: $parts[4], onEmpty: $parts[5], onError: $parts[6], errorFirst: $parts[7], inputSource: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $type,
            $path,
            $exists ?? Generators::constant(false),
            $collation ?? Generators::constant(null),
            $onEmpty ?? Generators::constant(null),
            $onError ?? Generators::constant(null),
            $errorFirst ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
