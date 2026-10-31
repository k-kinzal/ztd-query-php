<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

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
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator;
use Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates PathColumn values from the generators of its operands.
 *
 * @implements ErisGenerator<PathColumn>
 */
final class PathColumnGenerator implements ErisGenerator
{
    /** @var ErisGenerator<PathColumn> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of PathColumn from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): PathColumn => new PathColumn(name: $parts[0], type: $parts[1], path: $parts[2], exists: $parts[3], collation: $parts[4] === null ? null : new CollationName($parts[4]), onEmpty: $parts[5], onError: $parts[6], errorFirst: $parts[7] && $parts[5] !== null && $parts[6] !== null, inputSource: $parts[8]),
            Generators::tuple(
                new NameGenerator($release),
                new DeclaredNameGenerator($release),
                new StringLiteralGenerator($release),
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new JsonResponseGenerator($release)),
                Generators::oneOf(Generators::constant(null), new JsonResponseGenerator($release)),
                Generators::bool(),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (PathColumn $value) use ($release): bool {
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
     * @return GeneratedValueSingle<PathColumn>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<PathColumn>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
