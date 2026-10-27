<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Catalog;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SystemTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable>
 */
final class SystemTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable> */
    use Composition;

    /**
     * @param ErisGenerator<string> $schema Generated constructor operand
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<string> $type Generated constructor operand
     * @param ErisGenerator<string|null> $engine Generated constructor operand
     * @param ErisGenerator<int|null> $version Generated constructor operand
     * @param ErisGenerator<string|null> $rowFormat Generated constructor operand
     * @param ErisGenerator<string|null> $collation Generated constructor operand
     * @param ErisGenerator<string> $options Generated constructor operand
     * @param ErisGenerator<string> $comment Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn>> $columns Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Declaration\Table> $declaration Generated constructor operand
     */
    public function __construct(ErisGenerator $schema, ErisGenerator $name, ErisGenerator $type, ErisGenerator $engine, ErisGenerator $version, ErisGenerator $rowFormat, ErisGenerator $collation, ErisGenerator $options, ErisGenerator $comment, ErisGenerator $columns, ErisGenerator $declaration)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable(schema: $parts[0], name: $parts[1], type: $parts[2], engine: $parts[3], version: $parts[4], rowFormat: $parts[5], collation: $parts[6], options: $parts[7], comment: $parts[8], columns: $parts[9], declaration: $parts[10]);
        }, \Tests\Generator\Operands::tuple(
            $schema,
            $name,
            $type,
            $engine,
            $version,
            $rowFormat,
            $collation,
            $options,
            $comment,
            $columns,
            $declaration
        ));
    }

    /**
     * Selects a release catalog table and composes its complete ordered column metadata.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable>
     */
    public static function catalog(): ErisGenerator
    {
        $tables = \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::of(\SqlSemantics\Contract\GrammarRelease::MySql847)->tables;
        return \Tests\Generator\Operands::bind(Generators::elements($tables), static function (\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable $table): self {
            $columns = array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn $column) => SystemColumnGenerator::catalog(Generators::constant($column)), $table->columns);
            return new self(
                Generators::constant($table->schema),
                Generators::constant($table->name),
                Generators::constant($table->type),
                Generators::constant($table->engine),
                Generators::constant($table->version),
                Generators::constant($table->rowFormat),
                Generators::constant($table->collation),
                Generators::constant($table->options),
                Generators::constant($table->comment),
                Generators::tuple($columns),
                Generators::constant($table->declaration),
            );
        });
    }

}
