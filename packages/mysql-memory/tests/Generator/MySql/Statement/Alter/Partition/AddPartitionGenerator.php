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
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Partition\PartitionDefinitionGenerator;
use Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator;

/**
 * Generates AddPartition values from the generators of its operands.
 *
 * @implements ErisGenerator<AddPartition>
 */
final class AddPartitionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AddPartition> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AddPartition => new AddPartition(binlog: $parts[0], definitions: $parts[1], count: $parts[1] === [] ? $parts[2] : null, inputSource: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => count(array_unique(array_map(static fn ($definition): int => count($definition->subpartitions), ($parts[1])))) <= 1,
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new BinlogOptionGenerator($release)),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new PartitionDefinitionGenerator($release, $depth - 1))) : Generators::constant([]),
                    Generators::oneOf(Generators::constant(null), new NumeralGenerator($release)),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (AddPartition $value) use ($release): bool {
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
     * @return GeneratedValue<AddPartition>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<AddPartition> $element The generated counterexample
     * @return GeneratedValue<AddPartition>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
