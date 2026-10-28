<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;
use Tests\Generator\MySql\Statement\Table\IndexWordGenerator;

/**
 * Composes IndexDefinition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition>
 */
final class IndexDefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart>> $parts Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\ColumnName|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm|null>|null $algorithm Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName|null>|null $constraint Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\IndexWord|null>|null $keyword Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $typeKeyword The generated algorithm keyword
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $parts, ?ErisGenerator $name = null, ?ErisGenerator $algorithm = null, ?ErisGenerator $options = null, ?ErisGenerator $constraint = null, ?ErisGenerator $keyword = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $typeKeyword = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $kind,
            $parts,
            $name ?? Generators::constant(null),
            $algorithm ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $constraint ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $typeKeyword ?? Generators::bool()
        ), static function (array $parts) use ($keyword): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $selected): \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition => new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition(kind: $parts[0], parts: $parts[1], name: $parts[2], algorithm: $parts[3], options: $parts[4], constraint: $parts[5], keyword: $selected, inputSource: $parts[6], typeKeyword: $parts[7] && $parts[3] !== null), $keyword ?? ($parts[0] === \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary ? Generators::constant(null) : ($parts[0] === \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index ? new IndexWordGenerator() : \Tests\Generator\Operands::oneOf([Generators::constant(null), new IndexWordGenerator()]))));
        });
    }

    /**
     * Generates an index together with the column declaration its part names.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function tables(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $requests = \Tests\Generator\Operands::bind(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release), static function (\SqlSemantics\Platform\MySql\Statement\Name\ColumnName $column): ErisGenerator {
            $indexes = new self(
                Generators::constant(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index),
                Generators::vector(1, new ColumnPartGenerator(Generators::constant($column->column))),
                \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator::fromOperands(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::constant(null), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted)),
                keyword: new IndexWordGenerator(),
            );
            return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition $index) use ($column): \SqlSemantics\Platform\MySql\Statement\Table\CreateTable {
                $definition = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition($column, new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
                return new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($column->table ?? new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target')), [$definition, $index]);
            }, $indexes);
        });
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests);
    }
}
