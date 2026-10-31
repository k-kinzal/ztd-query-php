<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Dml\DeleteOptionGenerator;
use Tests\Generator\MySql\Statement\Dml\MultipleDeleteFormGenerator;
use Tests\Generator\MySql\Statement\Dml\Target\DeletionInputGenerator;
use Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator;
use Tests\Generator\MySql\Statement\Query\With\WithGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredMultipleDelete values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredMultipleDelete>
 */
final class DeferredMultipleDeleteGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredMultipleDelete> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeferredMultipleDelete from the generators of its components.
     * The deferred form exists only around a derived or JSON table, which the table sources produce from depth two on, so its input keeps that depth.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $readOnly = static function (DeletionInput $input): array {
            $names = [];
            foreach ((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($input->tables) as $occurrence) {
                if ($occurrence->alias !== null && !$occurrence instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference) {
                    $name = new QualifiedName($occurrence->alias);
                    if ((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->readOnly([$name], $input->tables) !== null) {
                        $names[] = $name;
                    }
                }
            }

            return $names;
        };
        $this->delegate = Generators::bind(
            Generators::suchThat(static fn (DeletionInput $input): bool => $readOnly($input) !== [], new DeletionInputGenerator($release, max(2, $depth - 1))),
            static fn (DeletionInput $input): ErisGenerator => Generators::map(
                static fn (array $parts): DeferredMultipleDelete => new DeferredMultipleDelete(with: $parts[0], options: $parts[1], input: new DeletionInput($input->tables, [$parts[6]], null, null), form: $parts[2], where: $parts[3], hints: $parts[4], inputSource: $parts[5]),
                Generators::tuple(
                    $depth > 0 && ($bit & 508) !== 0 ? Generators::oneOf(Generators::constant(null), new WithGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new DeleteOptionGenerator($release))),
                    new MultipleDeleteFormGenerator($release),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                    ($bit & 510) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new OptimizerHintGenerator($release))) : Generators::constant([]),
                    Generators::constant(null),
                    Generators::elements($readOnly($input))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredMultipleDelete $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeferredMultipleDelete>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeferredMultipleDelete>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
