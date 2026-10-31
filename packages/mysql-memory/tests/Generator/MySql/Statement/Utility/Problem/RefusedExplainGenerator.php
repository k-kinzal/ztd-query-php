<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredExplainGenerator;

/**
 * Generates RefusedExplain values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedExplain>
 */
final class RefusedExplainGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedExplain> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedExplain from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedExplain => new RefusedExplain(explanation: $parts[0]),
            Generators::suchThat(
                static fn (array $parts): bool => $parts[0]->into !== null
                    || ($parts[0]->analyze && strcasecmp($parts[0]->format->value ?? '', 'TRADITIONAL') === 0)
                    || $parts[0]->statement instanceof DeferredInsertRows
                    || $parts[0]->statement instanceof DeferredInsertSet
                    || $parts[0]->statement instanceof DeferredInsertQuery
                    || $parts[0]->statement instanceof DeferredQuery
                    || $parts[0]->statement instanceof DeferredSelection,
                Generators::tuple(
                    new DeferredExplainGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedExplain $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedExplain>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedExplain>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
