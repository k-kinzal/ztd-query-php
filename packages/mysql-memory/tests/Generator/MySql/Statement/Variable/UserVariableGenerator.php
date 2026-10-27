<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UserVariable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable>
 */
final class UserVariableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(name: $parts[0], scalarInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates a write and a locally bound read of the same session variable.
     * No context derivation or SQL parsing supplies the generated read's meaning.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function assignmentSequence(): ErisGenerator
    {
        $bindings = new UserVariableBindingGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::constant(true));
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($bindings, UserVariableEntryGenerator::integers(), Generators::choose(0, 100)), static function (array $parts): ErisGenerator {
            [$binding, $entry, $value] = $parts;
            $type = new \SqlSemantics\Statement\Type\Known($entry->domain);
            $fact = new \SqlSemantics\Statement\Fact\ScalarFact($type, \SqlSemantics\Statement\Type\Nullability::Nullable, $binding);
            $expressions = new self(Generators::constant($binding->name), Generators::constant($fact));
            $literal = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $value, new \SqlSemantics\Statement\Fact\ScalarFact($type, \SqlSemantics\Statement\Type\Nullability::NotNull));
            $target = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable($binding->name, $fact);
            $write = new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables(
                [new \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment($target, $literal)],
                new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), connection: $entry->collation),
            );
            return \Tests\Generator\Operands::tuple(Generators::constant($write), \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar($expressions));
        });
    }

    /**
     * Generates a read whose session state was deliberately left unspecified.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function missingState(): ErisGenerator
    {
        $state = new \Tests\Generator\Semantics\Statement\Reference\Missing\SessionStateGenerator(Generators::map(static fn (int $suffix): string => 'user variable @missing_' . $suffix, Generators::choose(0, 32)));
        return \Tests\Generator\Operands::bind($state, static function (\SqlSemantics\Statement\Reference\Missing\SessionState $missing): ErisGenerator {
            $name = new \SqlSemantics\Statement\Identifier\Name(substr($missing->subject, strlen('user variable @')));
            $fact = new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\DependentGenerator(Generators::constant([$missing])), \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::Dependent]));
            return \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar(new self(Generators::constant($name), $fact));
        });
    }

}
