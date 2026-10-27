<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UserSpecification values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification>
 */
final class UserSpecificationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|\SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\Identification|null>|null $identification Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\Identification>>|null $factors Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\Identification|null>|null $initial Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $replace Generated constructor operand
     * @param ErisGenerator<bool>|null $retainCurrent Generated constructor operand
     * @param ErisGenerator<bool>|null $discardOld Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $user, ?ErisGenerator $identification = null, ?ErisGenerator $factors = null, ?ErisGenerator $initial = null, ?ErisGenerator $replace = null, ?ErisGenerator $retainCurrent = null, ?ErisGenerator $discardOld = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification {
            return new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(user: $parts[0], identification: $parts[1], factors: $parts[2], initial: $parts[3], replace: $parts[4], retainCurrent: $parts[5], discardOld: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $user,
            $identification ?? Generators::constant(null),
            $factors ?? Generators::constant([]),
            $initial ?? Generators::constant(null),
            $replace ?? Generators::constant(null),
            $retainCurrent ?? Generators::constant(false),
            $discardOld ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
