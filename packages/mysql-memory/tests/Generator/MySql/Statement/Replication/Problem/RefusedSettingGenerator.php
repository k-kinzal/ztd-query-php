<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;

/**
 * Generates RefusedSetting values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedSetting>
 */
final class RefusedSettingGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedSetting> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedSetting => new RefusedSetting(error: $parts[0], number: match ($parts[0]) {
                ReplicationError::FileNumberOutOfRange => $parts[1] ?? new Numeral('0'), ReplicationError::DelayOutOfRange => $parts[1], default => null
            }, text: match ($parts[0]) {
                ReplicationError::LineFeed => new Text("line\nfeed"), ReplicationError::InvalidUuid => new Text('not-a-uuid'), default => null
            }),
            Generators::tuple(
                new ReplicationErrorGenerator($release),
                Generators::oneOf(Generators::constant(null), new NumeralGenerator($release)),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedSetting $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedSetting>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedSetting> $element The generated counterexample
     * @return GeneratedValue<RefusedSetting>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
