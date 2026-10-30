<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

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
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning;
use Tests\Generator\MySql\Statement\Partition\Method\DeferredMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\HashMethodGenerator;
use Tests\Generator\MySql\Statement\Partition\Method\KeyMethodGenerator;

/**
 * Generates Subpartitioning values from the generators of its operands.
 *
 * @implements ErisGenerator<Subpartitioning>
 */
final class SubpartitioningGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Subpartitioning> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Subpartitioning => new Subpartitioning(method: $parts[0], count: $parts[1] === null ? null : new Numeral((string) $parts[1]), inputSource: null),
            Generators::tuple(
                Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new HashMethodGenerator($release, $depth - 1),
                    1 => Generators::suchThat(static fn (KeyMethod $method): bool => $method->columns !== [], new KeyMethodGenerator($release)),
                    default => Generators::suchThat(static fn (DeferredMethod $method): bool => $method->kind === null, new DeferredMethodGenerator($release, $depth - 1)),
                }),
                Generators::oneOf(Generators::constant(null), Generators::choose(1, 9))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Subpartitioning $value) use ($release): bool {
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
     * @return GeneratedValue<Subpartitioning>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Subpartitioning> $element The generated counterexample
     * @return GeneratedValue<Subpartitioning>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
