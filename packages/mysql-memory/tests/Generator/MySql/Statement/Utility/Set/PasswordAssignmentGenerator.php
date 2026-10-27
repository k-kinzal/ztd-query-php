<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PasswordAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment>
 */
final class PasswordAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Password> $password Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $password, ?ErisGenerator $user = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment(password: $parts[0], user: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $password,
            $user ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
