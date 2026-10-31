<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;
use Tests\Support\Analysis;

/**
 * Generates HandlerScan values from the generators of its operands.
 *
 * @implements ErisGenerator<HandlerScan>
 */
final class HandlerScanGenerator implements ErisGenerator
{
    /** @var ErisGenerator<HandlerScan> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of HandlerScan from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): HandlerScan => new HandlerScan(handler: $parts[0], direction: $parts[1], where: $parts[2], limit: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[2]) === null || (new HandlerOperands())->problem(($parts[2])) === null,
                Generators::tuple(
                    new OpenHandlerGenerator($release),
                    new ScanDirectionGenerator($release),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (HandlerScan $statement) use ($release): HandlerScan {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof HandlerScan, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (HandlerScan $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (HandlerScan $value) use ($release): bool {
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
     * @return GeneratedValueSingle<HandlerScan>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<HandlerScan>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
