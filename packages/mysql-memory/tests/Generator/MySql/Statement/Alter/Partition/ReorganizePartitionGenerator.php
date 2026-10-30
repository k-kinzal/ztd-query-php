<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Partition\NamedPartitionsGenerator;
use Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator;
use Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator;

/**
 * Generates ReorganizePartition values from the generators of its operands.
 *
 * @implements ErisGenerator<ReorganizePartition>
 */
final class ReorganizePartitionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ReorganizePartition> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ReorganizePartition => new ReorganizePartition(binlog: $parts[0], partitions: $parts[1], definitions: $parts[1] === null ? [] : [$parts[2][0] ?? new PartitionDefinition(new Name('p'), null, [], []), ...array_slice($parts[2], 1)], inputSource: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => count(array_unique(array_map(static fn ($definition): int => count($definition->subpartitions), ($parts[1] === null ? [] : [$parts[2][0] ?? new PartitionDefinition(new Name('p'), null, [], []), ...array_slice($parts[2], 1)])))) <= 1,
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new BinlogOptionGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new NamedPartitionsGenerator($release)),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new PartitionDefinitionGenerator($release, $depth - 1))),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ReorganizePartition $value) use ($release): bool {
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
     * @return GeneratedValue<ReorganizePartition>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ReorganizePartition> $element The generated counterexample
     * @return GeneratedValue<ReorganizePartition>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
