<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes SetResourceGroup values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup>
 */
final class SetResourceGroupGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup> */
    use Composition;

    /**
     * Generates complete SetResourceGroup values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No SetResourceGroup forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup) {
                throw new LogicException('Publishing SetResourceGroup must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>>|null $threads Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $threadsCommas Generated list boundaries
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup>
     */
    public static function operands(ErisGenerator $name, ?ErisGenerator $threads = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $threadsCommas = null): ErisGenerator
    {
        return Generators::bind(Generators::tuple($name, $threads ?? Generators::constant([]), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)), static function (array $parts) use ($threadsCommas): ErisGenerator {
            return Generators::map(static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup => new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup(name: $parts[0], threads: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], threadsCommas: $commas), $threadsCommas ?? Generators::vector(max(0, count($parts[1]) - 1), Generators::bool()));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup>>
     */
    public static function forms(): iterable
    {
        yield 'SetResourceGroup/73' => SetResourceGroupGenerator::operands(
            name: new ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            threads: Generators::constant([])
        );
    }

}
