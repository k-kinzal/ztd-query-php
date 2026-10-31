<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Utility\Show\ShowLikeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowWhereGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowCharacterSet values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowCharacterSet>
 */
final class ShowCharacterSetGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowCharacterSet> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ShowCharacterSet from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowCharacterSet => new ShowCharacterSet(filter: $parts[0], relationInformation: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], form: $parts[4]),
            Generators::tuple(
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ShowLikeGenerator($release),
                    1 => new ShowWhereGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null),
                Generators::elements(array_values(array_filter(CharsetForm::cases(), static fn (CharsetForm $form): bool => $form->named())))
            )
        );
        $this->delegate = Generators::map(
            static function (ShowCharacterSet $statement) use ($release): ShowCharacterSet {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ShowCharacterSet, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowCharacterSet $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ShowCharacterSet $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ShowCharacterSet>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ShowCharacterSet>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
