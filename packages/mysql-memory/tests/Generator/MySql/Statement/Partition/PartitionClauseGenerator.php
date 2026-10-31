<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\ColumnsMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\DeferredMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\ExpressionMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\HashMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator;

/**
 * Generates PartitionClause values from the generators of its operands.
 *
 * @implements ErisGenerator<PartitionClause>
 */
final class PartitionClauseGenerator implements ErisGenerator
{
    /** @var ErisGenerator<PartitionClause> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of PartitionClause from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): PartitionClause => new PartitionClause(method: $parts[0], partitions: $parts[1], subpartitioning: $parts[2], definitions: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(
                static fn (array $parts): bool => count(array_unique(array_map(static fn ($definition): int => count($definition->subpartitions), ($parts[3])))) <= 1 && (($parts[2])?->count === null || ($parts[3]) === [] || in_array(count(($parts[3])[0]->subpartitions), [0, (int) ($parts[2])->count->text], true)),
                Generators::tuple(
                    Generators::bind(Generators::choose(0, 4), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ColumnsMethodGenerator($release),
                        1 => new DeferredMethodGenerator($release, $depth - 1),
                        2 => new ExpressionMethodGenerator($release, $depth - 1),
                        3 => new HashMethodGenerator($release, $depth - 1),
                        4 => new KeyMethodGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::oneOf(Generators::constant(null), new NumeralGenerator($release)),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new SubpartitioningGenerator($release, $depth - 1)) : Generators::constant(null),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new PartitionDefinitionGenerator($release, $depth - 1))) : Generators::constant([]),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (PartitionClause $value) use ($release): bool {
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
     * @return GeneratedValueSingle<PartitionClause>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<PartitionClause>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
