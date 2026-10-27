<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedGrantLevel values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel>
 */
final class RefusedGrantLevelGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest> $request Generated constructor operand
     * @param ErisGenerator<bool>|null $definition Generated constructor operand
     */
    public function __construct(ErisGenerator $request, ?ErisGenerator $definition = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel(request: $parts[0], definition: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $request,
            $definition ?? Generators::constant(false)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\RefusedGrantLevel' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedGrantLevelGenerator(
            request: new \Tests\Generator\MySql\Statement\Account\Privilege\Request\GrantRequestGenerator(
                privileges: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator(
                        kind: \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select]),
                        columns: Generators::constant([])
                    )]),
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator(
                        kind: \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Insert]),
                        columns: \Tests\Generator\Operands::tuple(
                            \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            )
                        )
                    )]),
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator(
                        kind: \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::CreateRole]),
                        columns: Generators::constant([])
                    )]),
                    new \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator(
                        kind: \Tests\Generator\MySql\Statement\Account\Privilege\Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::DropRole]),
                        columns: Generators::constant([])
                    )])
                ),
                kind: \Tests\Generator\MySql\Statement\Account\Privilege\Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
                level: new \Tests\Generator\MySql\Statement\Account\Privilege\Level\PrivilegeLevelGenerator([new \Tests\Generator\MySql\Statement\Account\Privilege\Level\GlobalLevelGenerator()]),
                grantees: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                        user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                            user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                value: Generators::map(static fn (int $suffix): string => 'u' . $suffix, Generators::choose(0, 8)),
                                quote: Generators::constant(null)
                            ),
                            host: Generators::constant(null)
                        )]),
                        identification: Generators::constant(null)
                    )
                ),
                tls: Generators::constant(null),
                options: Generators::constant([]),
                as: Generators::constant(null)
            ),
            definition: Generators::constant(false)
        )];
    }

}
