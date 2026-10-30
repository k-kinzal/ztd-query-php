<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ViewQueries;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates RefusedBody values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedBody>
 */
final class RefusedBodyGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedBody> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedBody => new RefusedBody(view: $parts[0], alter: $parts[1], body: $parts[2], definer: $parts[3]),
            Generators::suchThat(
                static fn (array $parts): bool => (new ViewQueries())->problem(($parts[2])) !== null,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    Generators::bool(),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new QueryGenerator($release),
                        1 => new DeferredSelectionGenerator($release),
                        2 => new DeferredQueryGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedBody $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedBody>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedBody> $element The generated counterexample
     * @return GeneratedValue<RefusedBody>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
