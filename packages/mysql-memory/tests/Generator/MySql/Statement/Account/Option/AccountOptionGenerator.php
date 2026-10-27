<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AccountOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption>
 */
final class AccountOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $number Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $number = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(kind: $parts[0], number: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $number ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
