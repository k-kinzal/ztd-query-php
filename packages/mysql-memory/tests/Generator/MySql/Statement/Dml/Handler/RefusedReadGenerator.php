<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates RefusedRead values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedRead>
 */
final class RefusedReadGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedRead> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedRead => new RefusedRead(handler: $parts[0], mode: $parts[1], index: $parts[1] instanceof ScanDirection ? null : $parts[2] ?? new Name('i'), values: $parts[1] instanceof KeyComparison ? [$parts[6], ...$parts[3]] : [], where: $parts[1] instanceof KeyComparison ? $parts[4] : $parts[6], limit: $parts[5]),
            Generators::tuple(
                new OpenHandlerGenerator($release),
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScanDirectionGenerator($release),
                    1 => new IndexDirectionGenerator($release),
                    2 => new KeyComparisonGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release),
                    1 => new DefaultRequestGenerator($release),
                    2 => new RowOperandGenerator($release),
                    3 => new DeferredOperandGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release),
                    1 => new RowOperandGenerator($release),
                    2 => new DeferredOperandGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                })),
                Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                new DeferredOperandGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedRead $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedRead>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedRead> $element The generated counterexample
     * @return GeneratedValue<RefusedRead>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
