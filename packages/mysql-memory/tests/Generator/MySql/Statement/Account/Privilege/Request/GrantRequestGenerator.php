<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Request;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GrantRequest values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest>
 */
final class GrantRequestGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege>> $privileges Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel> $level Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\Grantee|\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification>> $grantees Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement|null>|null $tls Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs|null>|null $as Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $privileges, ErisGenerator $kind, ErisGenerator $level, ErisGenerator $grantees, ?ErisGenerator $tls = null, ?ErisGenerator $options = null, ?ErisGenerator $as = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest(privileges: $parts[0], kind: $parts[1], level: $parts[2], grantees: $parts[3], tls: $parts[4], options: $parts[5], as: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $privileges,
            $kind,
            $level,
            $grantees,
            $tls ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $as ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
