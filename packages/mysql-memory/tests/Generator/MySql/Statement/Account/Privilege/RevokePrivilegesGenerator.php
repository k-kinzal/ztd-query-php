<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates routine wildcard revocations with independently generated recipients.
 *
 * @implements ErisGenerator<RevokePrivileges>
 */
final class RevokePrivilegesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RevokePrivileges> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct()
    {
        $operands = Generators::associative(['account' => new AccountNameGenerator(), 'kind' => Generators::elements(M\Account\Privilege\Level\ObjectKind::Function, M\Account\Privilege\Level\ObjectKind::Procedure)]);
        $this->delegate = Generators::map(
            static function (array $data): RevokePrivileges {
                if (!$data['account'] instanceof M\Name\AccountName || !$data['kind'] instanceof M\Account\Privilege\Level\ObjectKind) {
                    throw new LogicException('Revocation operands require an account and object kind.');
                }
                return new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], $data['kind'], new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification($data['account'])]);
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RevokePrivileges>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RevokePrivileges>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
