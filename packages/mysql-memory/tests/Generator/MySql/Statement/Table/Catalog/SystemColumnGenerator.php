<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Catalog;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SystemColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn>
 */
final class SystemColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> $domain Generated constructor operand
     * @param ErisGenerator<bool> $nullable Generated constructor operand
     * @param ErisGenerator<int> $flags Generated constructor operand
     * @param ErisGenerator<string> $originalName Generated constructor operand
     * @param ErisGenerator<string> $originalTable Generated constructor operand
     * @param ErisGenerator<string> $database Generated constructor operand
     * @param ErisGenerator<string> $columnType Generated constructor operand
     * @param ErisGenerator<string|null> $default Generated constructor operand
     * @param ErisGenerator<string> $key Generated constructor operand
     * @param ErisGenerator<string> $extra Generated constructor operand
     * @param ErisGenerator<string> $comment Generated constructor operand
     * @param ErisGenerator<string|null> $collation Generated constructor operand
     * @param ErisGenerator<bool> $listedNullable Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\ColumnDimensions> $dimensions Generated declared type dimensions
     */
    public function __construct(ErisGenerator $name, ErisGenerator $domain, ErisGenerator $nullable, ErisGenerator $flags, ErisGenerator $originalName, ErisGenerator $originalTable, ErisGenerator $database, ErisGenerator $columnType, ErisGenerator $default, ErisGenerator $key, ErisGenerator $extra, ErisGenerator $comment, ErisGenerator $collation, ErisGenerator $listedNullable, ErisGenerator $dimensions)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn(name: $parts[0], domain: $parts[1], nullable: $parts[2], flags: $parts[3], originalName: $parts[4], originalTable: $parts[5], database: $parts[6], columnType: $parts[7], default: $parts[8], key: $parts[9], extra: $parts[10], comment: $parts[11], collation: $parts[12], listedNullable: $parts[13], dimensions: $parts[14]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $domain,
            $nullable,
            $flags,
            $originalName,
            $originalTable,
            $database,
            $columnType,
            $default,
            $key,
            $extra,
            $comment,
            $collation,
            $listedNullable,
            $dimensions
        ));
    }

    /**
     * Preserves the coupled metadata of one selected catalog column.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn> $columns Columns selected by the enclosing table
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn>
     */
    public static function catalog(ErisGenerator $columns): ErisGenerator
    {
        return \Tests\Generator\Operands::bind($columns, static fn (\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn $column): self => new self(
            Generators::constant($column->name),
            Generators::constant($column->domain),
            Generators::constant($column->nullable),
            Generators::constant($column->flags),
            Generators::constant($column->originalName),
            Generators::constant($column->originalTable),
            Generators::constant($column->database),
            Generators::constant($column->columnType),
            Generators::constant($column->default),
            Generators::constant($column->key),
            Generators::constant($column->extra),
            Generators::map(static fn (int $value): string => 'generated comment ' . $value, \Tests\Generator\Operands::sized(Generators::nat(), 1000)),
            Generators::constant($column->collation),
            Generators::constant($column->listedNullable),
            ColumnDimensionsGenerator::catalog(Generators::constant($column->dimensions)),
        ));
    }

}
