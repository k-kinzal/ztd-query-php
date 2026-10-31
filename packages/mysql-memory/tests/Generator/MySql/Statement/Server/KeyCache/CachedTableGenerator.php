<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\KeyCache;

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
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;
use Tests\Generator\MySql\Statement\Partition\AllPartitionsGenerator;
use Tests\Generator\MySql\Statement\Partition\NamedPartitionsGenerator;
use Tests\Generator\MySql\Statement\Relation\Hint\PrimaryIndexGenerator;
use Tests\Generator\MySql\Statement\Table\IndexWordGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates CachedTable values from the generators of its operands.
 *
 * @implements ErisGenerator<CachedTable>
 */
final class CachedTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CachedTable> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CachedTable from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CachedTable => new CachedTable(table: $parts[0], partitions: $parts[1], indexes: $parts[2], inputSource: $parts[3], relationInformation: $parts[4], keyword: $parts[5]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new AllPartitionsGenerator($release),
                    1 => new NamedPartitionsGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                })),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new NameGenerator($release),
                    1 => new PrimaryIndexGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new IndexWordGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CachedTable $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CachedTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CachedTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
