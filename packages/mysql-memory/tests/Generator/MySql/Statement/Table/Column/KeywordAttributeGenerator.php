<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes KeywordAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute>
 */
final class KeywordAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword> $keyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $keyAlternative The generated written alternative
     */
    public function __construct(ErisGenerator $keyword, ?ErisGenerator $inputSource = null, ?ErisGenerator $keyAlternative = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute(keyword: $parts[0], inputSource: $parts[1], keyAlternative: $parts[2] && in_array($parts[0], [\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::PrimaryKey, \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::Unique], true));
        }, \Tests\Generator\Operands::tuple(
            $keyword,
            $inputSource ?? Generators::constant(null),
            $keyAlternative ?? Generators::bool()
        ));
    }
}
