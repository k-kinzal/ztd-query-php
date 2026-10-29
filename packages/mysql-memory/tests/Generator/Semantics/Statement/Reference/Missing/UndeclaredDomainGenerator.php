<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UndeclaredDomain values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Missing\UndeclaredDomain>
 */
final class UndeclaredDomainGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Missing\UndeclaredDomain> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Missing\UndeclaredDomain {
            return new \SqlSemantics\Statement\Reference\Missing\UndeclaredDomain(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Generates a host parameter with an unavailable named domain.
     * Its execution binding supplies the concrete value and domain; the missing
     * declaration must not be replaced by a guessed numeric or string type.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function parameterQueries(): ErisGenerator
    {
        $domains = new self(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()));
        $parameters = new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator(
            scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\DependentGenerator(\Tests\Generator\Operands::vector(1, $domains)), Generators::constant(\SqlSemantics\Statement\Type\Nullability::Dependent), new \Tests\Generator\MySql\Statement\Variable\ParameterBindingGenerator(Generators::constant(0))),
            position: Generators::constant(0),
        );
        return \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar($parameters);
    }

}
