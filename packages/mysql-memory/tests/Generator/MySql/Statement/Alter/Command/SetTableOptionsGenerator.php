<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use Tests\Generator\MySql\Statement\Table\Option\AutoextendSizeOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CharsetOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\CollationOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\EngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\InsertMethodOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\NumberOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\RowFormatOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\SecondaryEngineOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StartTransactionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\StorageOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TablespaceOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\TextOptionGenerator;
use Tests\Generator\MySql\Statement\Table\Option\UnionOptionGenerator;

/**
 * Generates SetTableOptions values from the generators of its operands.
 *
 * @implements ErisGenerator<SetTableOptions>
 */
final class SetTableOptionsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SetTableOptions> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): SetTableOptions => new SetTableOptions(options: $parts[0], inputSource: $parts[1]),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511, 511, 511, 511, 511, 508, 508, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new AutoextendSizeOptionGenerator($release),
                    1 => new CharsetOptionGenerator($release),
                    2 => new CollationOptionGenerator($release),
                    3 => new EngineOptionGenerator($release),
                    4 => new InsertMethodOptionGenerator($release),
                    5 => new NumberOptionGenerator($release),
                    6 => new RowFormatOptionGenerator($release),
                    7 => new SecondaryEngineOptionGenerator($release),
                    8 => new StartTransactionGenerator($release),
                    9 => new StorageOptionGenerator($release),
                    10 => new TablespaceOptionGenerator($release),
                    11 => new TextOptionGenerator($release),
                    12 => new UnionOptionGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SetTableOptions $value) use ($release): bool {
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
     * @return GeneratedValue<SetTableOptions>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<SetTableOptions> $element The generated counterexample
     * @return GeneratedValue<SetTableOptions>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
