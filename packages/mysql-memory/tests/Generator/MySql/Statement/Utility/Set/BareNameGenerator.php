<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes BareName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName>
 */
final class BareNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $word Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $qualifier Generated constructor operand
     * @param ErisGenerator<bool>|null $system Generated constructor operand
     * @param ErisGenerator<int>|null $parentheses Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $word, ?ErisGenerator $qualifier = null, ?ErisGenerator $system = null, ?ErisGenerator $parentheses = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName(word: $parts[0], qualifier: $parts[1], system: $parts[2], parentheses: $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $word,
            $qualifier ?? Generators::constant(null),
            $system ?? Generators::constant(true),
            $parentheses ?? Generators::constant(0),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates scoped and unscoped assignments of a bare boolean word.
     * The latter retains the dependency on an enclosing program's declarations.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables>
     */
    public static function booleanAssignments(): ErisGenerator
    {
        $words = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::elements(['ON', 'OFF']), Generators::constant(null));
        return Generators::bind(Generators::tuple($words, Generators::bool()), static function (array $parts): ErisGenerator {
            [$word, $scoped] = $parts;
            $types = $scoped
                ? new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(new \Tests\Generator\MySql\Statement\Type\StringFamilyGenerator())
                : new \Tests\Generator\Semantics\Statement\Type\DependentGenerator(Generators::vector(1, new \Tests\Generator\MySql\Statement\Utility\Problem\ProgramVariableGenerator(Generators::constant($word))));
            $value = new self(Generators::constant($word), system: Generators::constant($scoped), scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator($types, Generators::constant($scoped ? \SqlSemantics\Statement\Type\Nullability::NotNull : \SqlSemantics\Statement\Type\Nullability::Dependent)));
            $assignment = new NameAssignmentGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('autocommit'), Generators::constant(null)), $value, scope: Generators::constant($scoped ? \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Session : null));
            return SetVariablesGenerator::operands(Generators::vector(1, $assignment), new \Tests\Generator\MySql\Statement\StatementSemanticsGenerator(new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant(\SqlSemantics\Contract\GrammarRelease::MySql847))));
        });
    }

}
