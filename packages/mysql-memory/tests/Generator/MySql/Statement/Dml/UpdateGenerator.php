<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;

/**
 * Generates scalar and row-valued UPDATE assignments without a catalog.
 *
 * @implements ErisGenerator<Update>
 */
final class UpdateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Update> */
    private ErisGenerator $delegate;

    /**
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct(GrammarRelease $release)
    {
        $operands = Generators::associative(['column' => new ColumnNameGenerator($release), 'number' => Generators::choose(0, 10000), 'row' => Generators::bool()]);
        $this->delegate = Generators::map(
            static function (array $data): Update {
                if (!$data['column'] instanceof M\Name\ColumnName || !is_int($data['number']) || !is_bool($data['row'])) {
                    throw new LogicException('UPDATE operands require a column name, integer value and row flag.');
                }
                $column = new M\Name\ColumnUse($data['column']->column);
                $number = new M\Literal\NumberLiteral((string) $data['number']);
                $assignment = $data['row']
                    ? new M\Routine\Program\Write\DeferredColumnAssignment($column, new M\Expression\Row([$number, new M\Literal\NumberLiteral('2')]))
                    : new M\Dml\Assignment($column, $number);
                return new Update(null, false, false, [new M\Relation\TableReference(new QualifiedName(new Name('target')))], [$assignment]);
            },
            $operands,
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Update>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Update>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<M\Query\WithClause|null> $with The generated semantic operand
     * @param ErisGenerator<bool> $lowPriority The generated semantic operand
     * @param ErisGenerator<bool> $ignore The generated semantic operand
     * @param ErisGenerator<list<M\Relation\TableSource>> $tables The generated semantic operand
     * @param ErisGenerator<list<M\Dml\Assignment|M\Routine\Program\Write\DeferredColumnAssignment>> $assignments The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|M\Expression\RowOperand|M\Routine\Program\Expression\DeferredOperand|null> $where The generated semantic operand
     * @param ErisGenerator<list<M\Query\OrderItem|M\Routine\Program\Selection\DeferredOrderItem>> $orderBy The generated semantic operand
     * @param ErisGenerator<M\Query\Limit|null> $limit The generated semantic operand
     * @param ErisGenerator<list<M\Hint\OptimizerHint>> $hints The generated semantic operand
     * @return ErisGenerator<Update>
     */
    public static function fromOperands(ErisGenerator $with, ErisGenerator $lowPriority, ErisGenerator $ignore, ErisGenerator $tables, ErisGenerator $assignments, ErisGenerator $where, ErisGenerator $orderBy, ErisGenerator $limit, ErisGenerator $hints): ErisGenerator
    {
        return Generators::map(static fn (array $parts): Update => new Update(with: $parts[0], lowPriority: $parts[1], ignore: $parts[2], tables: $parts[3], assignments: $parts[4], where: $parts[5], orderBy: $parts[6], limit: $parts[7], hints: $parts[8]), \Tests\Generator\Operands::tuple($with, $lowPriority, $ignore, $tables, $assignments, $where, $orderBy, $limit, $hints));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<Update>>
     */
    public static function forms(): iterable
    {
        yield 'Update/128' => UpdateGenerator::fromOperands(
            with: Generators::constant(null),
            lowPriority: Generators::constant(false),
            ignore: Generators::constant(false),
            tables: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Relation\TableSourceGenerator([new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(
                    name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        schema: Generators::constant(null),
                        catalog: Generators::constant(null)
                    ),
                    alias: Generators::constant(null),
                    partitions: Generators::constant([]),
                    indexHints: Generators::constant([]),
                    sample: Generators::constant(null),
                    mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([M\Name\AliasMark::As]),
                    dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                )])
            ),
            assignments: \Tests\Generator\Operands::tuple(
                new AssignmentGenerator(
                    column: new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        qualifier: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    ),
                    value: new DefaultRequestGenerator()
                )
            ),
            where: Generators::constant(null),
            orderBy: Generators::constant([]),
            limit: Generators::constant(null),
            hints: Generators::constant([])
        );
    }

}
