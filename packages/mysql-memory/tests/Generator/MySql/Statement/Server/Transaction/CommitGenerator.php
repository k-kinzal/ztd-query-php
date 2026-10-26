<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static function (array $data) use ($release): Commit {
            [$choices, $work] = $data;
            if (!is_array($choices) || !is_bool($work)) {
                throw new LogicException('Completion generators provide two optional flags and WORK.');
            }
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
}
