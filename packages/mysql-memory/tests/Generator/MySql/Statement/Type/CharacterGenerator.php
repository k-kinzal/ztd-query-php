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
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\CharacterAlias|null>|null $alias Generated type keyword
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $length = null, ?ErisGenerator $national = null, ?ErisGenerator $charset = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::bind(Generators::tuple($kind, $national ?? Generators::constant(false)), static function (array $selected) use ($length, $charset, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Character {
                return new \SqlSemantics\Platform\MySql\Statement\Type\Character(kind: $parts[0], length: $parts[1], national: $parts[2], charset: $parts[3], inputSource: $parts[4], alias: $parts[5]);
            }, Generators::tuple(
                Generators::constant($selected[0]),
                $length ?? Generators::constant(null),
                Generators::constant($selected[1]),
                $charset ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? Keyword\CharacterAliasGenerator::forKind($selected[0], $selected[1])
            ));
        });
    }
}
