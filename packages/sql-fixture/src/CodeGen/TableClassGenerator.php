<?php

declare(strict_types=1);

namespace SqlFixture\CodeGen;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

/**
 * Emits the class that stands for one table.
 *
 * The class carries the table and column names as constants, hands out typed
 * endpoints for building plans, and takes overrides as named arguments. That
 * last part is the point: a misspelt column becomes an unknown named argument
 * and a wrong value becomes a type error, both at the call site, where today
 * a misspelt override key is silently dropped and a wrong value silently kept.
 */
final class TableClassGenerator
{
    private PhpIdentifier $identifier;
    private PhpTypeMapper $types;

    public function __construct(?PhpIdentifier $identifier = null, ?PhpTypeMapper $types = null)
    {
        $this->identifier = $identifier ?? new PhpIdentifier();
        $this->types = $types ?? new PhpTypeMapper();
    }

    public function className(TableSchema $schema): string
    {
        return $this->identifier->className($schema->tableName);
    }

    public function generate(TableSchema $schema, string $namespace): string
    {
        $class = $this->className($schema);
        $rowType = $class . 'Row';

        $sections = [
            $this->constants($schema),
            $this->columnRefs($schema),
            $this->overrides($schema),
            $this->readers($rowType),
        ];

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace ' . $namespace . ';',
            '',
            'use SqlFixture\Fixture\FixtureSet;',
            'use SqlFixture\Fixture\TableOverrides;',
            'use SqlFixture\Plan\ColumnRef;',
            '',
            $this->classDoc($schema, $rowType),
            'final class ' . $class,
            '{',
            implode("\n\n", array_filter($sections, static fn (string $s): bool => $s !== '')),
            '}',
            '',
        ]);
    }

    private function classDoc(TableSchema $schema, string $rowType): string
    {
        $lines = ['/**'];
        $lines[] = ' * Generated from the schema of `' . $schema->tableName . '`. Do not edit by hand.';
        $lines[] = ' *';
        $lines[] = ' * @phpstan-type ' . $rowType . ' array{';

        foreach ($schema->columns as $column) {
            $lines[] = ' *     ' . $column->name . ': ' . $this->types->documentedType($column) . ',';
        }

        $lines[] = ' * }';
        $lines[] = ' */';

        return implode("\n", $lines);
    }

    private function constants(TableSchema $schema): string
    {
        $lines = ['    public const TABLE = ' . $this->quote($schema->tableName) . ';', ''];

        foreach ($schema->columns as $column) {
            $lines[] = '    public const ' . $this->identifier->constantName($column->name)
                . ' = ' . $this->quote($column->name) . ';';
        }

        return implode("\n", $lines);
    }

    private function columnRefs(TableSchema $schema): string
    {
        $methods = [];

        foreach ($schema->columns as $column) {
            $method = $this->identifier->parameterName($column->name);
            $constant = $this->identifier->constantName($column->name);

            $methods[] = implode("\n", [
                '    public static function ' . $method . '(): ColumnRef',
                '    {',
                '        return ColumnRef::of(self::TABLE, self::' . $constant . ');',
                '    }',
            ]);
        }

        return implode("\n\n", $methods);
    }

    private function overrides(TableSchema $schema): string
    {
        $parameters = [];
        $entries = [];
        $docs = [];

        foreach ($schema->columns as $column) {
            $parameter = $this->identifier->parameterName($column->name);
            $native = $this->types->nativeType($column);

            $parameters[] = '        ' . ($native === 'mixed' ? 'mixed' : '?' . $native)
                . ' $' . $parameter . ' = null,';
            $entries[] = '            self::' . $this->identifier->constantName($column->name)
                . ' => $' . $parameter . ',';

            $documented = $this->types->overrideType($column);
            if ($documented !== $native . '|null') {
                $docs[] = '     * @param ' . $documented . ' $' . $parameter;
            }
        }

        $doc = [
            '    /**',
            '     * Column values to use instead of generated ones.',
            '     *',
            '     * A null argument leaves the column to the generator. To set one to NULL',
            '     * on purpose, call withNull() on the result.',
        ];
        if ($docs !== []) {
            $doc[] = '     *';
            $doc = [...$doc, ...$docs];
        }
        $doc[] = '     */';

        return implode("\n", [
            ...$doc,
            '    public static function overrides(',
            ...$parameters,
            '    ): TableOverrides {',
            '        return TableOverrides::of([',
            ...$entries,
            '        ]);',
            '    }',
        ]);
    }

    private function readers(string $rowType): string
    {
        return implode("\n", [
            '    /**',
            '     * @return list<' . $rowType . '>',
            '     */',
            '    public static function rows(FixtureSet $fixtures): array',
            '    {',
            '        /** @var list<' . $rowType . '> */',
            '        return $fixtures->rows(self::TABLE);',
            '    }',
            '',
            '    /**',
            '     * @return ' . $rowType . '|null',
            '     */',
            '    public static function row(FixtureSet $fixtures): ?array',
            '    {',
            '        /** @var ' . $rowType . '|null */',
            '        return $fixtures->row(self::TABLE);',
            '    }',
        ]);
    }

    private function quote(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }
}
