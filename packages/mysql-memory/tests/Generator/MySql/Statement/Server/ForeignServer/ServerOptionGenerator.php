<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\ForeignServer;

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
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates ServerOption values from the generators of its operands.
 *
 * @implements ErisGenerator<ServerOption>
 */
final class ServerOptionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ServerOption> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new ServerOptionKindGenerator($release),
            static fn (ServerOptionKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): ServerOption => new ServerOption(kind: $kind, value: $parts[0], inputSource: null),
                Generators::tuple($kind === ServerOptionKind::Port ? new NumeralGenerator($release) : new TextGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ServerOption $value) use ($release): bool {
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
     * @return GeneratedValue<ServerOption>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ServerOption> $element The generated counterexample
     * @return GeneratedValue<ServerOption>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
