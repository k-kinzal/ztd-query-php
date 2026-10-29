<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes AlterResourceGroup values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup>
 */
final class AlterResourceGroupGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup> */
    use Composition;

    /**
     * Generates complete AlterResourceGroup values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No AlterResourceGroup forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup) {
                throw new LogicException('Publishing AlterResourceGroup must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange>>|null $cpus Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority|null>|null $priority Generated constructor operand
     * @param ErisGenerator<bool|null>|null $enabled Generated constructor operand
     * @param ErisGenerator<bool>|null $force Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $cpusCommas Generated list boundaries
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $cpusOperator Generated clause notation
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $priorityOperator Generated clause notation
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup>
     */
    public static function operands(ErisGenerator $name, ?ErisGenerator $cpus = null, ?ErisGenerator $priority = null, ?ErisGenerator $enabled = null, ?ErisGenerator $force = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $cpusCommas = null, ?ErisGenerator $cpusOperator = null, ?ErisGenerator $priorityOperator = null): ErisGenerator
    {
        return Generators::bind(Generators::tuple($name, $cpus ?? Generators::constant([]), $priority ?? Generators::constant(null), $enabled ?? Generators::constant(null), $force ?? Generators::constant(false), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $cpusOperator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional(), $priorityOperator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional()), static function (array $parts) use ($cpusCommas): ErisGenerator {
            return Generators::map(static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup => new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup(name: $parts[0], cpus: $parts[1], priority: $parts[2], enabled: $parts[3], force: $parts[4], statementInformation: $parts[5], inputSource: $parts[6], cpusOperator: $parts[7], priorityOperator: $parts[8], cpusCommas: $commas), $cpusCommas ?? Generators::vector(max(0, count($parts[1]) - 1), Generators::bool()));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup>>
     */
    public static function forms(): iterable
    {
        yield 'AlterResourceGroup/7' => AlterResourceGroupGenerator::operands(
            name: new ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            cpus: Generators::constant([]),
            priority: Generators::constant(null),
            enabled: Generators::constant(null),
            force: Generators::constant(false)
        );
        yield 'AlterResourceGroup/423' => AlterResourceGroupGenerator::operands(
            name: new ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            cpus: Generators::tuple(
                new CpuRangeGenerator(
                    first: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => '00' . $value, Generators::choose(0, 4))),
                    last: Generators::constant(null)
                )
            ),
            priority: Generators::constant(null),
            enabled: Generators::constant(null),
            force: Generators::constant(false)
        );
        yield 'AlterResourceGroup/424' => AlterResourceGroupGenerator::operands(
            name: new ResourceGroupNameGenerator(
                spelling: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            ),
            cpus: Generators::constant([]),
            priority: new ThreadPriorityGenerator(
                negative: Generators::constant(false),
                number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                    text: Generators::constant('1'),
                    hexadecimal: Generators::constant(false)
                )
            ),
            enabled: Generators::constant(null),
            force: Generators::constant(false)
        );
    }

}
