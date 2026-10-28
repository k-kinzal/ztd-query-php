<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SystemAssignment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment>
 */
final class SystemAssignmentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget> $variable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName|\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $variable, ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment(variable: $parts[0], value: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $variable,
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Composes both LOCAL assignment forms with reads of the local, session and global values.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, string}>
     */
    public static function localAssignments(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $values = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $number): string => (string) $number, Generators::choose(1, 10)));
        return \Tests\Generator\Operands::bind($values, static function (\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral $value) use ($release): ErisGenerator {
            return Generators::map(static function (bool $system) use ($value, $release): array {
                $local = \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Local;
                $name = new \SqlSemantics\Statement\Identifier\Name('auto_increment_increment');
                $assignment = $system
                    ? new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget($name, $local), $value)
                    : new \SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment($name, $value, $local);
                $write = new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables([$assignment]);
                $items = [];
                foreach ([$local, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Session, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global] as $scope) {
                    $items[] = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable(new \SqlSemantics\Statement\Identifier\Name('auto_increment_increment'), $scope));
                }
                $read = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $items));
                $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
                $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
                return [$publication->publish($context, $write), $publication->publish($context, $read), $value->text];
            }, Generators::bool());
        });
    }
}
