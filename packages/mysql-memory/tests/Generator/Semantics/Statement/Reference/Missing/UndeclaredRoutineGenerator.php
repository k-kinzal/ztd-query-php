<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UndeclaredRoutine values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine>
 */
final class UndeclaredRoutineGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine {
            return new \SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Generates a stored-function invocation whose return declaration is unavailable.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function queries(): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('pbt')));
        $calls = \Tests\Generator\Operands::bind(new self($names), static fn (\SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine $missing): \Tests\Generator\MySql\Statement\Call\FunctionCallGenerator => new \Tests\Generator\MySql\Statement\Call\FunctionCallGenerator(
            Generators::constant($missing->name->name),
            schema: Generators::constant($missing->name->schema),
            scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\DependentGenerator(Generators::constant([$missing])), Generators::constant(\SqlSemantics\Statement\Type\Nullability::Dependent)),
        ));
        return \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar($calls);
    }

}
