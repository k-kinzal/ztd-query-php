<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedSelectionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates RefusedQuery values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedQuery>
 */
final class RefusedQueryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedQuery> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedQuery from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedQuery => new RefusedQuery(view: $parts[0], alter: $parts[1], query: $parts[2], definer: $parts[3]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                Generators::bool(),
                Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator($release),
                    1 => new RefusedSelectionGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::oneOf(Generators::constant(null), new AccountGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedQuery $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedQuery>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedQuery>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
