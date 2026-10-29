<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes FieldLookup values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\FieldLookup>
 */
final class FieldLookupGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\FieldLookup> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Shape\FieldLookup>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }

    /**
     * Composes output lookups with executable projections, preserving duplicate aliases and open stars.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, \SqlSemantics\Statement\Shape\FieldLookup, string}>
     */
    public static function queries(): ErisGenerator
    {
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
            Generators::map(static fn (int $number): string => (string) $number, Generators::choose(0, 100)),
            new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer())), \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::NotNull])),
        );
        $fields = FieldGenerator::namedExpression($numbers);
        $ambiguous = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($fields, $numbers), static function (array $parts): AmbiguousFieldsGenerator {
            [$first, $second] = $parts;
            \SqlSemantics\Diagnostic\Check::invariant($first->name !== null, 'The generated duplicate outputs have a named alias.');
            $other = new \SqlSemantics\Statement\Shape\Field(1, $first->slot, $second);
            return new AmbiguousFieldsGenerator(Generators::constant($first->name->value), Generators::constant([$first, $other]));
        });
        $missing = new \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredRelationGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('pbt'), Generators::constant(null))));
        $lookups = new self([
            new UniqueFieldGenerator($fields), $ambiguous,
            new AbsentFieldGenerator(Generators::map(static fn (int $suffix): string => 'absent_' . $suffix, Generators::choose(0, 100))),
            new DependentFieldGenerator(Generators::map(static fn (int $suffix): string => 'unknown_' . $suffix, Generators::choose(0, 100)), Generators::constant([]), \Tests\Generator\Operands::vector(1, $missing)),
        ]);
        return \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple($lookups, $numbers), static function (array $parts): ErisGenerator {
            [$lookup, $number] = $parts;
            $names = \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive;
            $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
            if ($lookup instanceof \SqlSemantics\Statement\Shape\DependentField) {
                $missing = $lookup->missing[0];
                \SqlSemantics\Diagnostic\Check::invariant($missing instanceof \SqlSemantics\Statement\Reference\Missing\UndeclaredRelation, 'The generated open star depends on one undeclared relation.');
                $tables = new \Tests\Generator\Semantics\Statement\Reference\Table\UndeclaredTableGenerator(Generators::constant($missing));
                $stars = new OpenStarGenerator(Generators::constant($lookup->missing));
                return Generators::map(static function (array $parts) use ($lookup, $missing, $names, $profile): array {
                    [$table, $star] = $parts;
                    $relation = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($missing->name, relationInformation: new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([], $lookup->missing), $table));
                    $output = new \SqlSemantics\Statement\Fact\QueryFact([$star], $names);
                    $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], $relation, queryInformation: $output), new \SqlSemantics\Platform\MySql\Statement\StatementSemantics($profile, output: $output));
                    return [$query, $lookup, $lookup->name];
                }, \Tests\Generator\Operands::tuple($tables, $stars));
            }
            if ($lookup instanceof \SqlSemantics\Statement\Shape\UniqueField) {
                $fields = [$lookup->field];
                $name = $lookup->field->name;
                \SqlSemantics\Diagnostic\Check::invariant($name !== null, 'The generated unique output is named.');
                $searched = $name->value;
                $orders = Generators::map(static function (\SqlSemantics\Statement\Reference\Column\AliasTarget $target) use ($name): array {
                    $expression = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($name, scalarInformation: new \SqlSemantics\Statement\Fact\ScalarFact($target->field->type, $target->field->nullability, $target));
                    return [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem($expression)];
                }, new \Tests\Generator\Semantics\Statement\Reference\Column\AliasTargetGenerator(Generators::constant($lookup->field)));
            } elseif ($lookup instanceof \SqlSemantics\Statement\Shape\AmbiguousFields) {
                $fields = $lookup->fields;
                $searched = $lookup->name;
                $orders = Generators::constant([]);
            } else {
                \SqlSemantics\Diagnostic\Check::invariant($lookup instanceof \SqlSemantics\Statement\Shape\AbsentField, 'The remaining lookup is an absent name.');
                $fact = $number->scalarInformation();
                $fields = [new \SqlSemantics\Statement\Shape\Field(0, new \SqlSemantics\Statement\Shape\OutputSlot(new \SqlSemantics\Statement\Identifier\Name('present_' . $lookup->name), $fact->type, $fact->nullability), $number)];
                $searched = $lookup->name;
                $orders = Generators::constant([]);
            }
            $items = [];
            foreach ($fields as $field) {
                \SqlSemantics\Diagnostic\Check::invariant($field->expression !== null, 'The generated output retains its executable scalar.');
                $items[] = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($field->expression, $field->name);
            }
            $output = new \SqlSemantics\Statement\Fact\QueryFact($fields, $names);
            return Generators::map(static function (array $orders) use ($items, $output, $profile, $lookup, $searched): array {
                $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], $items, orderBy: $orders, queryInformation: $output), new \SqlSemantics\Platform\MySql\Statement\StatementSemantics($profile, output: $output, connection: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
                return [$query, $lookup, $searched];
            }, $orders);
        });
    }

}
