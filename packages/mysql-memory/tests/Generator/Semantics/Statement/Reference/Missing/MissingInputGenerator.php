<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

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
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use Tests\Generator\MySql\Statement\Query\Problem\NameConversionGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\RecursiveReferenceGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\ProgramVariableGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\TableOrViewGenerator;

/**
 * Generates MissingInput values from the generators of its operands.
 *
 * @implements ErisGenerator<MissingInput>
 */
final class MissingInputGenerator implements ErisGenerator
{
    /** @var ErisGenerator<MissingInput> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(Generators::choose(0, 9), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new NameConversionGenerator($release),
            1 => new RecursiveReferenceGenerator($release),
            2 => new ProgramVariableGenerator($release),
            3 => new TableOrViewGenerator($release),
            4 => new IncompleteMembersGenerator($release),
            5 => new SessionStateGenerator($release),
            6 => new UnboundParameterGenerator($release),
            7 => new UndeclaredDomainGenerator($release),
            8 => new UndeclaredRelationGenerator($release),
            9 => new UndeclaredRoutineGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (MissingInput $value) use ($release): bool {
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
     * @return GeneratedValue<MissingInput>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<MissingInput> $element The generated counterexample
     * @return GeneratedValue<MissingInput>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
