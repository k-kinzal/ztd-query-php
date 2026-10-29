<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Elementary values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Elementary>
 */
final class ElementaryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Elementary> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind> $kind Generated constructor operand
     * @param ErisGenerator<string|null>|null $length Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Keyword\ElementaryAlias|null>|null $alias Generated type keyword
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $length = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::bind($kind, static function (\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind $selected) use ($length, $inputSource, $alias): ErisGenerator {
            return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Elementary {
                return new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(kind: $parts[0], length: $parts[1], inputSource: $parts[2], alias: $parts[3]);
            }, Generators::tuple(
                Generators::constant($selected),
                $length ?? Generators::constant(null),
                $inputSource ?? Generators::constant(null),
                $alias ?? Keyword\ElementaryAliasGenerator::forKind($selected)
            ));
        });
    }
}
