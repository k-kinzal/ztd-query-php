<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Flush;

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
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates FlushItem values from the generators of its operands.
 *
 * @implements ErisGenerator<FlushItem>
 */
final class FlushItemGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FlushItem> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): FlushItem => new FlushItem(option: $parts[0], channel: $parts[0] === FlushOption::RelayLogs ? $parts[1] : null, inputSource: null),
            Generators::tuple(new FlushOptionGenerator($release), Generators::oneOf(Generators::constant(null), new TextGenerator($release)))
        );
        $this->delegate = Generators::suchThat(
            static function (FlushItem $value) use ($release): bool {
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
     * @return GeneratedValue<FlushItem>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<FlushItem> $element The generated counterexample
     * @return GeneratedValue<FlushItem>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
