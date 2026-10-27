<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Enumeration values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Enumeration>
 */
final class EnumerationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Enumeration> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\Text>> $members Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute|null>|null $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $members, ?ErisGenerator $charset = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Enumeration {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(kind: $parts[0], members: $parts[1], charset: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $members,
            $charset ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
