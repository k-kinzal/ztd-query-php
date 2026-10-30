<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Dml\ValueRoles;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Name\TableWildcardGenerator;
use Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator;
use Tests\Support\Analysis;
use Tests\Support\Operands;

/**
 * Generates Evaluation values from the generators of its operands.
 *
 * @implements ErisGenerator<Evaluation>
 */
final class EvaluationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Evaluation> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Evaluation => new Evaluation(items: [...($parts[3] ? [new Star()] : []), ...$parts[0]], statementInformation: $parts[1], inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => (static function (array $parts): bool {
                    foreach ($parts as $part) {
                        foreach (is_array($part) ? $part : [$part] as $node) {
                            if ($node instanceof Node && (new ValueRoles())->problem($node) !== null) {
                                return false;
                            }
                        }
                    }

                    return true;
                })($parts)
                && (Operands::aggregateFree($parts[0])),
                Generators::tuple(
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $wildcard): ErisGenerator => $wildcard ? new TableWildcardGenerator($release) : new SelectExpressionGenerator($release, $depth - 1)))),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Evaluation $value) use ($release): bool {
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
            static fn (Evaluation $statement): Evaluation => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (Evaluation $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<Evaluation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Evaluation> $element The generated counterexample
     * @return GeneratedValue<Evaluation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
