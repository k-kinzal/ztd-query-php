<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TlsCondition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition>
 */
final class TlsConditionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute> $attribute Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $and The generated written alternative
     */
    public function __construct(ErisGenerator $attribute, ErisGenerator $value, ?ErisGenerator $inputSource = null, ?ErisGenerator $and = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition(attribute: $parts[0], value: $parts[1], inputSource: $parts[2], and: $parts[3]);
        }, Generators::tuple(
            $attribute,
            $value,
            $inputSource ?? Generators::constant(null),
            $and ?? Generators::bool()
        ));
    }
}
