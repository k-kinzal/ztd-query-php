<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\TableChange\AlterationRoles;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Name\ColumnQualifiers;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\StandaloneCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TrailingCommand;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Alter\Column\AddColumnGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\AddColumnsGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\ChangeColumnGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\ColumnVisibilityGenerator;
use Tests\Generator\MySql\Statement\Alter\Column\DefaultSettingGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\AddConstraintGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ConstraintEnforcementGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ConvertCharsetGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\DropElementGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ForceGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\IndexVisibilityGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\RenameElementGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\RenameToGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ReorderGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\SetTableOptionsGenerator;
use Tests\Generator\MySql\Statement\Alter\Command\ToggleKeysGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\AlgorithmOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\LockOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Modifier\ValidationOptionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\AddPartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\CoalescePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\DropPartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\ExchangePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\MaintainPartitionsGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\PartitionByGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\RemovePartitioningGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\ReorganizePartitionGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\SecondaryLoadGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\TablespaceCommandGenerator;
use Tests\Generator\MySql\Statement\Alter\Partition\UpgradePartitioningGenerator;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredDefaultSettingGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates AlterTable values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterTable>
 */
final class AlterTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterTable> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): AlterTable => new AlterTable(table: $parts[0], commands: $parts[1], ignore: ($bit & 1) !== 0 && $parts[2], relationInformation: $parts[3], statementInformation: $parts[4], inputSource: $parts[5], dot: $parts[0]->schema === null && ($bit & 3) !== 0 ? $parts[6] : OptionalWords::Omitted),
            Generators::suchThat(
                static fn (array $parts): bool => (static function (array $commands): bool {
                    $last = count($commands) - 1;
                    foreach ($commands as $index => $command) {
                        if ($index !== $last && ($command instanceof TrailingCommand || $command instanceof StandaloneCommand || (!$command instanceof AlterModifier && $commands[$last] instanceof StandaloneCommand))) {
                            return false;
                        }
                    }

                    return true;
                })(($parts[1]))
                && ((new ColumnQualifiers())->accepts(($parts[0]), ($parts[1])))
                && (!(new AlterationRoles())->invalidValidation(($parts[1])) && !(new AlterationRoles())->transaction(($parts[1]))),
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 508, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 3, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new AddColumnGenerator($release, $depth - 1),
                        1 => new AddColumnsGenerator($release, $depth - 1),
                        2 => new ChangeColumnGenerator($release, $depth - 1),
                        3 => new ColumnVisibilityGenerator($release),
                        4 => new DefaultSettingGenerator($release, $depth - 1),
                        5 => new AddConstraintGenerator($release, $depth - 1),
                        6 => new ConstraintEnforcementGenerator($release),
                        7 => new ConvertCharsetGenerator($release),
                        8 => new DropElementGenerator($release),
                        9 => new ForceGenerator($release),
                        10 => new IndexVisibilityGenerator($release),
                        11 => new RenameElementGenerator($release),
                        12 => new RenameToGenerator($release),
                        13 => new ReorderGenerator($release),
                        14 => new SetTableOptionsGenerator($release),
                        15 => new ToggleKeysGenerator($release),
                        16 => new AlgorithmOptionGenerator($release),
                        17 => new LockOptionGenerator($release),
                        18 => new ValidationOptionGenerator($release),
                        19 => new AddPartitionGenerator($release, $depth - 1),
                        20 => new CoalescePartitionGenerator($release),
                        21 => new DropPartitionGenerator($release),
                        22 => new ExchangePartitionGenerator($release),
                        23 => new MaintainPartitionsGenerator($release),
                        24 => new PartitionByGenerator($release, $depth - 1),
                        25 => new RemovePartitioningGenerator($release),
                        26 => new ReorganizePartitionGenerator($release, $depth - 1),
                        27 => new SecondaryLoadGenerator($release),
                        28 => new TablespaceCommandGenerator($release),
                        29 => new UpgradePartitioningGenerator($release),
                        30 => new DeferredDefaultSettingGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))) : Generators::constant([]),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null),
                    new OptionalWordsGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AlterTable $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
        $this->delegate = Generators::map(
            static fn (AlterTable $statement): AlterTable => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (AlterTable $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<AlterTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AlterTable> $element The generated counterexample
     * @return GeneratedValue<AlterTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
