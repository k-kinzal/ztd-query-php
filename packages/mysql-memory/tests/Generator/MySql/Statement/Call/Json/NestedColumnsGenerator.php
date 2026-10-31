<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

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
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use Tests\Generator\MySql\Statement\Call\JsonTableColumnGenerator;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;

/**
 * Generates NestedColumns values from the generators of its operands.
 *
 * @implements ErisGenerator<NestedColumns>
 */
final class NestedColumnsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<NestedColumns> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of NestedColumns from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): NestedColumns => new NestedColumns(path: $parts[0], columns: $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                new StringLiteralGenerator($release),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new JsonTableColumnGenerator($release, $depth - 1))),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (NestedColumns $value) use ($release): bool {
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
     * @return GeneratedValueSingle<NestedColumns>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<NestedColumns>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
