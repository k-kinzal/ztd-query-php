<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FactorChange values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange>
 */
final class FactorChangeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction> $action Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep>> $steps Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $user, ErisGenerator $action, ErisGenerator $steps, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange {
            return new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange(user: $parts[0], action: $parts[1], steps: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $user,
            $action,
            $steps,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
