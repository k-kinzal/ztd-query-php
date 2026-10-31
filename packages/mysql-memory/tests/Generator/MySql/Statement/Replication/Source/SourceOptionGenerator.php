<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Source;

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
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates SourceOption values from the generators of its operands.
 *
 * @implements ErisGenerator<SourceOption>
 */
final class SourceOptionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SourceOption> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SourceOption from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $releases = new Releases();
        $terminology = $releases->source($release);
        $this->delegate = Generators::bind(
            Generators::elements(array_values(array_filter(SourceOptionKind::cases(), static fn (SourceOptionKind $kind): bool => $releases->option($release, $kind)))),
            static fn (SourceOptionKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): SourceOption => new SourceOption(terminology: $terminology, kind: $kind, value: $parts[0], synonym: $terminology === Terminology::Current && $kind->renamed() && $parts[1], inputSource: null),
                Generators::tuple(
                    Generators::suchThat(static fn ($value): bool => $kind->accepts($value), Generators::bind(Generators::choose(0, 7), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new TextGenerator($release),
                        1 => Generators::map(static fn (int $number): Numeral => new Numeral((string) $number), Generators::choose(0, 65535)),
                        2 => new NumberLiteralGenerator($release),
                        3 => new ServerIdsGenerator($release),
                        4 => new AccountNameGenerator($release),
                        5 => new PrimaryKeyCheckGenerator($release),
                        6 => new AnonymousGtidsGenerator($release),
                        default => Generators::constant(null),
                    })),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SourceOption $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SourceOption>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SourceOption>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
