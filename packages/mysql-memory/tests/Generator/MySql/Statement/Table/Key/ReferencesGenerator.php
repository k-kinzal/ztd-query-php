<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

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
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferentialAction;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates References values from the generators of its operands.
 *
 * @implements ErisGenerator<References>
 */
final class ReferencesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<References> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): References => new References(table: $parts[0], columns: $parts[1], match: $parts[2], actions: $parts[3] === null ? [] : array_values(array_filter([$parts[3], $parts[4]], static fn (?ReferentialAction $action): bool => $action !== null)), inputSource: null, relationInformation: null),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NameGenerator($release)))),
                Generators::oneOf(Generators::constant(null), new ReferenceMatchGenerator($release)),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (ReferenceOption $option): ReferentialAction => new ReferentialAction(ReferenceEvent::Update, $option), new ReferenceOptionGenerator($release))),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (ReferenceOption $option): ReferentialAction => new ReferentialAction(ReferenceEvent::Delete, $option), new ReferenceOptionGenerator($release)))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (References $value) use ($release): bool {
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
     * @return GeneratedValue<References>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<References> $element The generated counterexample
     * @return GeneratedValue<References>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
