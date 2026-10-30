<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Access;

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
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;

/**
 * Generates JsonExtraction values from the generators of its operands.
 *
 * @implements ErisGenerator<JsonExtraction>
 */
final class JsonExtractionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<JsonExtraction> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): JsonExtraction => new JsonExtraction(column: $parts[0], path: $parts[1], unquote: $parts[2], scalarInformation: $parts[3], inputSource: $parts[4]),
            Generators::tuple(
                new ColumnUseGenerator($release),
                Generators::map(static fn (array $parts): Text => new Text(match ($parts[0]) {
                    0 => '$', 1 => '$.key_' . $parts[1], default => '$[' . $parts[1] . ']'
                }), Generators::tuple(Generators::choose(0, 2), Generators::choose(0, 9))),
                Generators::bool(),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (JsonExtraction $value) use ($release): bool {
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
     * @return GeneratedValue<JsonExtraction>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<JsonExtraction> $element The generated counterexample
     * @return GeneratedValue<JsonExtraction>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
