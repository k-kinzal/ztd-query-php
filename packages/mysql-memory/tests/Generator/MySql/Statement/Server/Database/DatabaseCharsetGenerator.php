<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DatabaseCharset values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset>
 */
final class DatabaseCharsetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName> $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $operator Generated constructor operand
     * @param ErisGenerator<bool>|null $defaultKeyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm>|null $form Generated constructor operand
     */
    public function __construct(ErisGenerator $charset, ?ErisGenerator $inputSource = null, ?ErisGenerator $operator = null, ?ErisGenerator $defaultKeyword = null, ?ErisGenerator $form = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset(charset: $parts[0], inputSource: $parts[1], operator: $parts[2], defaultKeyword: $parts[3], form: $parts[4]);
        }, Generators::tuple(
            $charset,
            $inputSource ?? Generators::constant(null),
            $operator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional(),
            $defaultKeyword ?? Generators::bool(),
            $form ?? Generators::elements([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharacterSet, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::CharSet])
        ));
    }
}
