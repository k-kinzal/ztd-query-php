<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind;
use Tests\Generator\MySql\Statement\Partition\AllPartitionsGenerator;
use Tests\Generator\MySql\Statement\Partition\NamedPartitionsGenerator;
use Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator;
use Tests\Generator\MySql\Statement\Server\CheckOptionGenerator;
use Tests\Generator\MySql\Statement\Server\RepairOptionGenerator;

/**
 * Generates MaintainPartitions values from the generators of its operands.
 *
 * @implements ErisGenerator<MaintainPartitions>
 */
final class MaintainPartitionsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<MaintainPartitions> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of MaintainPartitions from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new MaintenanceKindGenerator($release),
            static fn (MaintenanceKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): MaintainPartitions => new MaintainPartitions(kind: $kind, binlog: $kind->logged() ? $parts[0] : null, partitions: $parts[1], options: match ($kind) {
                    MaintenanceKind::Check => $parts[2],
                    MaintenanceKind::Repair => $parts[3],
                    MaintenanceKind::Rebuild, MaintenanceKind::Optimize, MaintenanceKind::Analyze, MaintenanceKind::Truncate => [],
                }, ignoredBinlog: $kind === MaintenanceKind::Optimize ? $parts[4] : null, inputSource: null),
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new BinlogOptionGenerator($release)),
                    Generators::bind(Generators::bool(), static fn (bool $all): ErisGenerator => $all ? new AllPartitionsGenerator($release) : new NamedPartitionsGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new CheckOptionGenerator($release))),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new RepairOptionGenerator($release))),
                    Generators::oneOf(Generators::constant(null), new BinlogOptionGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (MaintainPartitions $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<MaintainPartitions>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<MaintainPartitions>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
