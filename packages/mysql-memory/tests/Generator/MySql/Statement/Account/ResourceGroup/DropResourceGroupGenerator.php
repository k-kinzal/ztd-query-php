<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropResourceGroup values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup>
 */
final class DropResourceGroupGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName> $name Generated constructor operand
     * @param ErisGenerator<bool>|null $force Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $force = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup {
            return new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup(name: $parts[0], force: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $force ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup>>
     */
    public static function forms(): iterable
    {
        yield 'DropResourceGroup/35' => new DropResourceGroupGenerator(
            name: new ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            force: Generators::constant(false)
        );
    }

}
