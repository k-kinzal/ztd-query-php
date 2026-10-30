<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Relation\Hint;

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
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction;
use Tests\Generator\MySql\Statement\Table\IndexWordGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates IndexHint values from the generators of its operands.
 *
 * @implements ErisGenerator<IndexHint>
 */
final class IndexHintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<IndexHint> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            new IndexHintActionGenerator($release),
            static fn (IndexHintAction $action): ErisGenerator => Generators::map(
                static fn (array $parts): IndexHint => new IndexHint(action: $action, scope: $parts[0], indexes: $parts[1], inputSource: null, keyword: $parts[2]),
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new IndexHintScopeGenerator($release)),
                    Generators::bind(Generators::choose($action === IndexHintAction::Use ? 0 : 1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $named): ErisGenerator => $named ? new NameGenerator($release) : new PrimaryIndexGenerator($release)))),
                    new IndexWordGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (IndexHint $value) use ($release): bool {
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
     * @return GeneratedValue<IndexHint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<IndexHint> $element The generated counterexample
     * @return GeneratedValue<IndexHint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
