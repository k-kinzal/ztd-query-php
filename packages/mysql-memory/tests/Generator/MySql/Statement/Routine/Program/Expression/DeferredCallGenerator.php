<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument;
use Tests\Generator\MySql\Statement\Call\CallArgumentGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates DeferredCall values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredCall>
 */
final class DeferredCallGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredCall> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredCall => new DeferredCall(name: $parts[0], arguments: [new DeferredCallArgument($parts[1]), ...array_map(static fn ($argument): CallArgument|DeferredCallArgument => $argument instanceof CallArgument ? new CallArgument($argument->expression) : new DeferredCallArgument($argument->expression), $parts[2])], schema: $parts[3], scalarInformation: null, inputSource: null),
            Generators::tuple(
                new NameGenerator($release),
                Generators::bind(Generators::bool(), static fn (bool $row): ErisGenerator => $row ? new RowOperandGenerator($release, $depth - 1) : new DeferredOperandGenerator($release, $depth - 1)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredCallArgumentGenerator($release, $depth - 1) : new CallArgumentGenerator($release, $depth - 1)))),
                new NameGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredCall $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredCall>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredCall> $element The generated counterexample
     * @return GeneratedValue<DeferredCall>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
