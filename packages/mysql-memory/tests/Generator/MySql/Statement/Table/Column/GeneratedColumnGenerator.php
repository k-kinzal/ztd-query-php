<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\Composition;

/**
 * Composes GeneratedColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn>
 */
final class GeneratedColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn> */
    use Composition;

    /**
     * @param ErisGenerator<M\Type\DeclaredName> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|M\Expression\RowOperand|M\Routine\Program\Expression\DeferredOperand> $expression Generated constructor operand
     * @param ErisGenerator<M\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<M\Table\Column\Kind\GeneratedStorage|null>|null $storage Generated constructor operand
     * @param ErisGenerator<list<M\Table\Column\ColumnAttribute>>|null $attributes Generated constructor operand
     * @param ErisGenerator<M\Table\Key\References|null>|null $references Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<M\Expression\OptionalWords>|null $generatedAlways Generated constructor operand
     */
    public function __construct(ErisGenerator $type, ErisGenerator $expression, ?ErisGenerator $collation = null, ?ErisGenerator $storage = null, ?ErisGenerator $attributes = null, ?ErisGenerator $references = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $generatedAlways = null)
    {
        $this->delegate = Generators::map(static function (array $parts): M\Table\Column\GeneratedColumn {
            return new M\Table\Column\GeneratedColumn(type: $parts[0], expression: $parts[1], collation: $parts[2], storage: $parts[3], attributes: $parts[4], references: $parts[5], inputSource: $parts[6], generatedAlways: $parts[7]);
        }, Generators::tuple(
            $type,
            $expression,
            $collation ?? Generators::constant(null),
            $storage ?? Generators::constant(null),
            $attributes ?? Generators::constant([]),
            $references ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $generatedAlways ?? Generators::constant(M\Expression\OptionalWords::Omitted)
        ));
    }
    /**
     * Composes generation expressions for both CREATE and dependent ALTER requests.
     *
     * @return non-empty-list<ErisGenerator<array{M\Statement, int}>>
     */
    public static function definitionForms(\SqlSemantics\Contract\GrammarRelease $release, M\Table\CreateTable $create, bool $alter): array
    {
        $column = $create->elements[0];
        \SqlSemantics\Diagnostic\Check::invariant($column instanceof M\Table\Column\ColumnDefinition, 'A generated table supplies the prerequisite column.');
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 1000)));
        $type = Generators::map(static fn (M\Type\DeclaredName $type): M\Type\DeclaredName => \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($type), Generators::constant($column->specification->dataType()));
        $forms = [
            self::withCondition($type, new \Tests\Generator\MySql\Statement\Literal\ParameterGenerator(), 3102),
            self::withCondition($type, new \Tests\Generator\MySql\Statement\Expression\Subquery\ScalarSubqueryGenerator(Generators::map(static fn (\SqlSemantics\Statement\Scalar $number): \SqlSemantics\Statement\Query => new M\Query\Select([], [new M\Query\SelectExpression($number)]), $numbers)), 3102),
        ];
        if ($release !== \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            $forms[] = self::withCondition($type, new \Tests\Generator\MySql\Statement\Expression\RowGenerator(Generators::vector(2, $numbers)), 3764);
            $forms[] = self::withCondition($type, new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(Generators::constant(M\Call\KeywordFunction::User), Generators::constant([])), 3763);
            $forms[] = self::withCondition($type, new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $value): string => 'v' . $value, Generators::choose(0, 1000)))), 3772);
            $attributes = Generators::oneOf(...[
                new DefaultLiteralGenerator($numbers),
                new DefaultExpressionGenerator($numbers),
                new OnUpdateGenerator(Generators::constant(new M\Table\Column\Value\CurrentTimestamp())),
                new KeywordAttributeGenerator(Generators::elements([M\Table\Column\Kind\ColumnKeyword::AutoIncrement, M\Table\Column\Kind\ColumnKeyword::SerialDefaultValue])),
                new FormatAttributeGenerator(Generators::elements(M\Table\Column\Kind\ColumnFormat::cases())),
                new StorageAttributeGenerator(Generators::elements(M\Table\Column\Kind\StorageMedium::cases())),
            ]);
            $forms[] = self::withCondition($type, $numbers, 1221, Generators::vector(1, $attributes));
            $forms[] = self::withCondition(Generators::constant(new M\Type\Elementary(M\Type\Kind\ElementaryKind::Serial)), $numbers, 1221);
        }
        $columns = Generators::oneOf(...$forms);
        return [
            Generators::map(static fn (array $input): array => self::definition($input[0], $input[1], $create, $column, $alter), $columns),
        ];
    }

    /**
     * Preserves the generation operands and their expected SQL error through shrinking.
     *
     * @param ErisGenerator<M\Type\DeclaredName> $type The dependent declared type
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|M\Expression\RowOperand|M\Routine\Program\Expression\DeferredOperand> $expression The generation expression
     * @param ErisGenerator<list<M\Table\Column\ColumnAttribute>>|null $attributes Incompatible attributes when present
     * @return ErisGenerator<array{M\Table\Column\GeneratedColumn, int}>
     */
    public static function withCondition(ErisGenerator $type, ErisGenerator $expression, int $expected, ?ErisGenerator $attributes = null): ErisGenerator
    {
        return Generators::map(static fn (M\Table\Column\GeneratedColumn $column): array => [$column, $expected], new self($type, $expression, storage: Generators::elements([null, ...M\Table\Column\Kind\GeneratedStorage::cases()]), attributes: $attributes, generatedAlways: new \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator()));
    }

    /**
     * Builds one complete dependent request from typed generated operands.
     *
     * @return array{M\Statement, int}
     */
    public static function definition(M\Table\Column\GeneratedColumn $specification, int $expected, M\Table\CreateTable $create, M\Table\Column\ColumnDefinition $column, bool $alter): array
    {
        $name = new M\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name($column->name->column->value . '_generated'));
        $generated = new M\Table\Column\ColumnDefinition($name, $specification);
        $request = $alter ? new M\Alter\AlterTable($create->name, [new M\Alter\Column\AddColumn($generated)]) : new M\Table\CreateTable($create->name, [$column, $generated]);
        return [$request, $expected];
    }

}
