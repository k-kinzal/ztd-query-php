<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete Commit values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<Commit>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Commit forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): Commit {
            if (!$statement instanceof Commit) {
                throw new LogicException('Publishing Commit must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Selects the grammar before generating operands.
     * @return ErisGenerator<Commit>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static function (array $data) use ($release): Commit {
            [$choices, $work] = $data;
            return new Commit($choices[0], $choices[1], $work, profile: new LanguageProfile($release));
        }, Generators::tuple(Generators::elements([null, null], [null, false], [null, true], [false, null], [false, false], [false, true], [true, null], [true, false]), Generators::bool()));
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
        return Generators::map(static fn (array $parts): Commit => new Commit(chain: $parts[0], release: $parts[1], work: $parts[2], profile: $parts[3], client: $parts[4]), Generators::tuple($chain, $release, $work, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<Commit>>
     */
    public static function forms(): iterable
    {
        yield 'transaction characteristics' => self::operands(GrammarRelease::MySql847);
    }

}
