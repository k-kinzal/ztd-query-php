<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

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
use SqlSemantics\Platform\MySql\Rules\Dml\ValueRoles;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;
use Tests\Support\Analysis;

/**
 * Generates ProcedureCall values from the generators of its operands.
 *
 * @implements ErisGenerator<ProcedureCall>
 */
final class ProcedureCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ProcedureCall> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ProcedureCall from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ProcedureCall => new ProcedureCall(procedure: $parts[0], arguments: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], parentheses: $parts[1] === [] ? $parts[4] : OptionalWords::Written),
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
                })($parts),
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))) : Generators::constant([]),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new OptionalWordsGenerator($release))
                )
            )
        );
        $this->delegate = Generators::map(
            static function (ProcedureCall $statement) use ($release): ProcedureCall {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ProcedureCall, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ProcedureCall $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ProcedureCall $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ProcedureCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ProcedureCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
