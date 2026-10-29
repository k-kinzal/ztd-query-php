<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RepeatedTlsAttribute values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute>
 */
final class RepeatedTlsAttributeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute> $attribute Generated constructor operand
     */
    public function __construct(ErisGenerator $attribute)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute(attribute: $parts[0]);
        }, Generators::tuple(
            $attribute
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\RepeatedTlsAttribute' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RepeatedTlsAttributeGenerator(
            attribute: \Tests\Generator\MySql\Statement\Account\Option\TlsAttributeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute::Subject])
        )];
    }

}
