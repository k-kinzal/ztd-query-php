<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Cursor;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates FetchCursor values from the generators of its operands.
 *
 * @implements ErisGenerator<FetchCursor>
 */
final class FetchCursorGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FetchCursor> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): FetchCursor => new FetchCursor(cursor: $parts[0], targets: $parts[1], inputSource: $parts[2], from: $parts[3], next: $parts[3] && $parts[4]),
            Generators::tuple(
                new NameGenerator($release),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NameGenerator($release))),
                Generators::constant(null),
                Generators::bool(),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FetchCursor $value) use ($release): bool {
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
     * @return GeneratedValue<FetchCursor>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<FetchCursor> $element The generated counterexample
     * @return GeneratedValue<FetchCursor>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
