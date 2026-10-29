<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ProgramRow values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow>
 */
final class ProgramRowGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Relation> $relation Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $names Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>> $domains Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $alias Generated constructor operand
     */
    public function __construct(ErisGenerator $relation, ErisGenerator $names, ErisGenerator $domains, ?ErisGenerator $alias = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow(relation: $parts[0], names: $parts[1], domains: $parts[2], alias: $parts[3]);
        }, Generators::tuple(
            $relation,
            $names,
            $domains,
            $alias ?? Generators::constant(null)
        ));
    }

    /**
     * Generates a parameter row, its defining routine and a call sharing that parameter identity.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure, \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall}>
     */
    public static function invocation(): ErisGenerator
    {
        $parameters = new \Tests\Generator\MySql\Statement\Routine\ParameterListGenerator(Generators::vector(1, new \Tests\Generator\MySql\Statement\Routine\ParameterGenerator(
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            new \Tests\Generator\MySql\Statement\Type\IntegralGenerator(\Tests\Generator\MySql\Statement\Type\Kind\IntegralKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int])),
        )));
        $rows = Generators::bind($parameters, static fn (\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList $parameters): self => new self(
            Generators::constant($parameters),
            Generators::constant([$parameters->parameters[0]->name]),
            Generators::vector(1, new \Tests\Generator\MySql\Statement\Type\Resolved\DomainGenerator(
                \Tests\Generator\MySql\Statement\Type\Resolved\KindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer]),
                \Tests\Generator\MySql\Statement\Type\Resolved\FieldGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long]),
                length: Generators::constant(11),
            )),
        ));
        return Generators::map(static function (array $parts): array {
            [$row, $name, $argument] = $parts;
            \SqlSemantics\Diagnostic\Check::invariant($row->relation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList, 'The generated program row declares parameters.');
            $body = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($row->names[0]), $row->names[0])]));
            $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure($name, $row->relation, $body);
            $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7', searchPath: new \SqlSemantics\Contract\SearchPath('pbt')))->context([]);
            $create = \SqlSemantics\Facade\Semantics::fromStatement($context, $definition);
            $argument = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral($argument->text, new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known($row->domains[0]), \SqlSemantics\Statement\Type\Nullability::NotNull));
            $call = new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall($name, [$argument], new \SqlSemantics\Platform\MySql\Statement\StatementSemantics($context->profile, connection: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            \SqlSemantics\Diagnostic\Check::invariant($create instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure, 'The generated dependency is a procedure definition and call.');
            return [$create, $call];
        }, Generators::tuple($rows, new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()), new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)))));
    }

}
