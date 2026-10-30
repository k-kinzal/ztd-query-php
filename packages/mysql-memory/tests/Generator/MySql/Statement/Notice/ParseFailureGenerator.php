<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Notice;

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
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator;

/**
 * Generates ParseFailure values from the generators of its operands.
 *
 * @implements ErisGenerator<ParseFailure>
 */
final class ParseFailureGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ParseFailure> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ParseFailure => new ParseFailure(problem: $parts[0], aborts: $parts[1], phase: $parts[2] === NoticePhase::Resolution ? NoticePhase::Syntax : $parts[2], endsProgramStatement: $parts[3]),
            Generators::tuple(
                new DiagnosticGenerator($release),
                Generators::bool(),
                new NoticePhaseGenerator($release),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ParseFailure $value) use ($release): bool {
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
     * @return GeneratedValue<ParseFailure>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ParseFailure> $element The generated counterexample
     * @return GeneratedValue<ParseFailure>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
