<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateResourceGroup values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup>
 */
final class CreateResourceGroupGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange>>|null $cpus Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority|null>|null $priority Generated constructor operand
     * @param ErisGenerator<bool|null>|null $enabled Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $kind, ?ErisGenerator $cpus = null, ?ErisGenerator $priority = null, ?ErisGenerator $enabled = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup {
            return new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup(name: $parts[0], kind: $parts[1], cpus: $parts[2], priority: $parts[3], enabled: $parts[4], statementInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $kind,
            $cpus ?? Generators::constant([]),
            $priority ?? Generators::constant(null),
            $enabled ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup>>
     */
    public static function forms(): iterable
    {
        yield 'CreateResourceGroup/24' => new CreateResourceGroupGenerator(
            name: new ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            kind: ResourceGroupKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind::User]),
            cpus: Generators::constant([]),
            priority: Generators::constant(null),
            enabled: Generators::constant(null)
        );
    }

}
