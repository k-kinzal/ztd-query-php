<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;

/**
 * Generates CastSizeInput values from the generators of its operands.
 *
 * @implements ErisGenerator<CastSizeInput>
 */
final class CastSizeInputGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CastSizeInput> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CastSizeInput => new CastSizeInput(problem: $parts[0], start: $parts[1], end: $parts[2]),
            Generators::tuple(
                Generators::map(static fn (InvalidTypeSize $problem): InvalidTypeSize => new InvalidTypeSize($problem->rule, '', $problem->actual), new InvalidTypeSizeGenerator($release)),
                Generators::choose(0, 100),
                Generators::choose(100, 200)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CastSizeInput $value) use ($release): bool {
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
     * @return GeneratedValue<CastSizeInput>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CastSizeInput> $element The generated counterexample
     * @return GeneratedValue<CastSizeInput>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
