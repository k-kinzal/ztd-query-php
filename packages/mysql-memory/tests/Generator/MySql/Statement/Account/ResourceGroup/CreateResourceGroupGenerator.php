<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete CreateResourceGroup values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No CreateResourceGroup forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup) {
                throw new LogicException('Publishing CreateResourceGroup must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange>>|null $cpus Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority|null>|null $priority Generated constructor operand
     * @param ErisGenerator<bool|null>|null $enabled Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<list<bool>>|null $cpusCommas Generated list boundaries
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $typeOperator Generated clause notation
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $cpusOperator Generated clause notation
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null>|null $priorityOperator Generated clause notation
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup>
     */
    public static function operands(ErisGenerator $name, ErisGenerator $kind, ?ErisGenerator $cpus = null, ?ErisGenerator $priority = null, ?ErisGenerator $enabled = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $cpusCommas = null, ?ErisGenerator $typeOperator = null, ?ErisGenerator $cpusOperator = null, ?ErisGenerator $priorityOperator = null): ErisGenerator
    {
        return Generators::bind(Generators::tuple($name, $kind, $cpus ?? Generators::constant([]), $priority ?? Generators::constant(null), $enabled ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $typeOperator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional(), $cpusOperator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional(), $priorityOperator ?? \Tests\Generator\MySql\Statement\AssignmentOperatorGenerator::optional()), static function (array $parts) use ($cpusCommas): ErisGenerator {
            return Generators::map(static fn (array $commas): \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup => new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup(name: $parts[0], kind: $parts[1], cpus: $parts[2], priority: $parts[3], enabled: $parts[4], statementInformation: $parts[5], inputSource: $parts[6], typeOperator: $parts[7], cpusOperator: $parts[8], priorityOperator: $parts[9], cpusCommas: $commas), $cpusCommas ?? Generators::vector(max(0, count($parts[2]) - 1), Generators::bool()));
        });
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup>>
     */
    public static function forms(): iterable
    {
        yield 'CreateResourceGroup/24' => CreateResourceGroupGenerator::operands(
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
