<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Support\Analysis;

/**
 * Generates InstallComponent values from the generators of its operands.
 *
 * @implements ErisGenerator<InstallComponent>
 */
final class InstallComponentGenerator implements ErisGenerator
{
    /** @var ErisGenerator<InstallComponent> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): InstallComponent => new InstallComponent(components: array_map(static fn (Text $component): Text => new Text('file://component_' . preg_replace('/[^a-z0-9_]/i', '_', $component->value)), $parts[0]), settings: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new TextGenerator($release))),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ComponentSettingGenerator($release, $depth - 1))) : Generators::constant([]),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (InstallComponent $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
        $this->delegate = Generators::map(
            static fn (InstallComponent $statement): InstallComponent => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (InstallComponent $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<InstallComponent>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<InstallComponent> $element The generated counterexample
     * @return GeneratedValue<InstallComponent>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
