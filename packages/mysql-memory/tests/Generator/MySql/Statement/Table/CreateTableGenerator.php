<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Generates INT declarations and duplicate-column requests, preserving target qualifiers.
 *
 * @implements ErisGenerator<CreateTable>
 */
final class CreateTableGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<CreateTable>
     */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<ColumnName> $columnNames The declaration names to exercise
     */
    public function __construct(ErisGenerator $columnNames, bool $duplicateColumns = false, bool $ifNotExists = false)
    {
        $this->delegate = Generators::map(
            static function (ColumnName $columnName) use ($duplicateColumns, $ifNotExists): CreateTable {
                $elements = [new ColumnDefinition($columnName, new OrdinaryColumn(new Integral(IntegralKind::Int)))];
                if ($duplicateColumns) {
                    $elements[] = new ColumnDefinition(new ColumnName($columnName->column, $columnName->table, $columnName->dot), new OrdinaryColumn(new Integral(IntegralKind::Int)));
                }
                return new CreateTable($columnName->table ?? new QualifiedName(new Name('target')), $elements, ifNotExists: $ifNotExists);
            },
            $columnNames,
        );
    }

    /**
     * @param int $size The generation size chosen by Eris
     * @return GeneratedValueSingle<CreateTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
