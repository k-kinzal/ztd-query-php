<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Dml\ValueRoles;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use Tests\Generator\MySql\Statement\Dml\AssignmentGenerator;
use Tests\Generator\MySql\Statement\Dml\DuplicateHandlingGenerator;
use Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\CharsetNameGenerator;
use Tests\Generator\MySql\Statement\Name\ColumnUseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredColumnAssignmentGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Support\Analysis;

/**
 * Generates LoadTable values from the generators of its operands.
 *
 * @implements ErisGenerator<LoadTable>
 */
final class LoadTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<LoadTable> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): LoadTable => new LoadTable(format: $parts[0], lock: $parts[1], input: $parts[2], duplicates: $parts[3], table: new WriteTarget($parts[4]->name, null, $parts[4]->partitions), charset: $parts[5] === null || $parts[5]->name !== null || ($bit & 3) !== 0 ? $parts[5] : null, compression: ($bit & 508) !== 0 ? $parts[6] : null, rowTag: $parts[7], fields: $parts[8], lines: $parts[9], ignored: $parts[10] === null ? null : new Numeral((string) $parts[10]), columns: $parts[11], assignments: $parts[12], bulk: $parts[13], statementInformation: $parts[14], inputSource: $parts[15], ignoreRows: $parts[16], charsetForm: $parts[17]),
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
                    new LoadFormatGenerator($release),
                    Generators::oneOf(Generators::constant(null), new LoadLockGenerator($release)),
                    new LoadInputGenerator($release),
                    Generators::oneOf(Generators::constant(null), new DuplicateHandlingGenerator($release)),
                    new WriteTargetGenerator($release),
                    Generators::oneOf(Generators::constant(null), new CharsetNameGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new FieldOptionGenerator($release))),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new LineOptionGenerator($release))),
                    Generators::oneOf(Generators::constant(null), Generators::choose(0, 9)),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ColumnUseGenerator($release),
                        1 => new UserVariableGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))),
                    $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new AssignmentGenerator($release, $depth - 1),
                        1 => new DeferredColumnAssignmentGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }))) : Generators::constant([]),
                    ($bit & 508) !== 0 ? Generators::oneOf(Generators::constant(null), new BulkOptionsGenerator($release)) : Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::bool(),
                    Generators::elements(array_values(array_filter(CharsetForm::cases(), static fn (CharsetForm $form): bool => $form->named())))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (LoadTable $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
        $this->delegate = Generators::map(
            static fn (LoadTable $statement): LoadTable => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (LoadTable $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<LoadTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<LoadTable> $element The generated counterexample
     * @return GeneratedValue<LoadTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
