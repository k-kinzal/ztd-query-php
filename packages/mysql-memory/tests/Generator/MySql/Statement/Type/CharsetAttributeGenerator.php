<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CharsetAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute>
 */
final class CharsetAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm> $form Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $form, ?ErisGenerator $charset = null, ?ErisGenerator $mark = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(form: $parts[0], charset: $parts[1], mark: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $form,
            $charset ?? Generators::constant(null),
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark::Absent),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
