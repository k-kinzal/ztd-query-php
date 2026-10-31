<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Handler;

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
use SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Dml\DefaultRequestGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates HandlerIndexSeek values from the generators of its operands.
 *
 * @implements ErisGenerator<HandlerIndexSeek>
 */
final class HandlerIndexSeekGenerator implements ErisGenerator
{
    /** @var ErisGenerator<HandlerIndexSeek> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of HandlerIndexSeek from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): HandlerIndexSeek => new HandlerIndexSeek(handler: $parts[0], index: $parts[1], comparison: $parts[2], values: $parts[3], where: $parts[4], limit: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]),
            Generators::suchThat(
                static fn (array $parts): bool => array_filter([...($parts[3]), ($parts[4])], static fn ($operand): bool => $operand instanceof Node && (new HandlerOperands())->problem($operand) !== null) === [],
                Generators::tuple(
                    new OpenHandlerGenerator($release),
                    new NameGenerator($release),
                    new KeyComparisonGenerator($release),
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new DefaultRequestGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (HandlerIndexSeek $statement) use ($release): HandlerIndexSeek {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof HandlerIndexSeek, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (HandlerIndexSeek $value) use ($release): bool {
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
     * @return GeneratedValueSingle<HandlerIndexSeek>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<HandlerIndexSeek>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
