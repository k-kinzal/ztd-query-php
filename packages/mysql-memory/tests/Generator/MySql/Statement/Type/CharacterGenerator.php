<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Character values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Character>
 */
final class CharacterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Character> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $length Generated constructor operand
     * @param ErisGenerator<bool>|null $national Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute|null>|null $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $length = null, ?ErisGenerator $national = null, ?ErisGenerator $charset = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Character {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Character(kind: $parts[0], length: $parts[1], national: $parts[2], charset: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $length ?? Generators::constant(null),
            $national ?? Generators::constant(false),
            $charset ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
