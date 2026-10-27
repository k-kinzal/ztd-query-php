<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;

/**
 * Composes Commit operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<Commit>
 */
final class CommitGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Commit> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static function (array $data) use ($release): Commit {
            [$choices, $work] = $data;
            return new Commit($choices[0], $choices[1], $work, profile: new LanguageProfile($release));
        }, \Tests\Generator\Operands::tuple(Generators::elements([null, null], [null, false], [null, true], [false, null], [false, false], [false, true], [true, null], [true, false]), Generators::bool()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Commit>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Commit>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<bool|null> $chain The generated semantic operand
     * @param ErisGenerator<bool|null> $release The generated semantic operand
     * @param ErisGenerator<bool> $work The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<Commit>
     */
    public static function fromOperands(ErisGenerator $chain, ErisGenerator $release, ErisGenerator $work, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): Commit => new Commit(chain: $parts[0], release: $parts[1], work: $parts[2], profile: $parts[3], client: $parts[4]), \Tests\Generator\Operands::tuple($chain, $release, $work, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<Commit>>
     */
    public static function forms(): iterable
    {
        yield 'Commit/21' => CommitGenerator::fromOperands(
            chain: Generators::constant(null),
            release: Generators::constant(null),
            work: Generators::constant(false),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
    }

}
