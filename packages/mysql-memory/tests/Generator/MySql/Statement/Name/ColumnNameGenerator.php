<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Generates valid declaration names without requiring an existing database state.
 * Names contain 1–64 characters, including quotes, spaces and Unicode, without a trailing space.
 *
 * @implements ErisGenerator<ColumnName>
 */
final class ColumnNameGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<ColumnName>
     */
    private ErisGenerator $delegate;

    /**
     * MySQL 5.x additionally accepts table qualifiers and a leading dot in column definitions.
     *
     * @throws InvalidArgumentException When the selected grammar is not MySQL
     */
    public function __construct(GrammarRelease $release)
    {
        $column = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(
            static fn (array $data): string => $data['prefix'] . $data['last'],
            Generators::associative([
                'prefix' => Generators::map(
                    static fn (array $characters): string => implode('', array_slice($characters, 0, 63)),
                    Generators::seq(Generators::elements('a', 'Z', '0', '_', '`', ' ', '.', 'é', '列')),
                ),
                'last' => Generators::elements('a', 'Z', '0', '_', '`', '.', 'é', '列'),
            ]),
        ), Generators::oneOf(Generators::constant(null), new \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)));
        $unqualified = Generators::map(static fn (Name $name): ColumnName => new ColumnName($name), $column);
        $table = Generators::map(static fn (int $id): Name => new Name('table_' . $id), \Tests\Generator\Operands::sized(Generators::nat(), 100000));
        $schema = Generators::map(static fn (int $id): Name => new Name('schema_' . $id), \Tests\Generator\Operands::sized(Generators::nat(), 100000));
        $qualified = \Tests\Generator\Operands::tuple($column, $table);
        $qualifiedSchema = \Tests\Generator\Operands::tuple($column, $table, $schema);
        $this->delegate = match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => \Tests\Generator\Operands::oneOf([
                $unqualified,
                Generators::map(static fn (Name $name): ColumnName => new ColumnName($name, dot: OptionalWords::Written), $column),
                Generators::map(static fn (array $data): ColumnName => new ColumnName($data[0], new QualifiedName($data[1])), $qualified),
                Generators::map(static fn (array $data): ColumnName => new ColumnName($data[0], new QualifiedName($data[1], $data[2])), $qualifiedSchema),
            ]),
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820,
            GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901,
            GrammarRelease::MySql910 => $unqualified,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => throw new InvalidArgumentException('ColumnName generation requires a MySQL grammar.'),
        };
    }

    /**
     * @param int $size The generation size chosen by Eris
     * @return GeneratedValueSingle<ColumnName>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ColumnName>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Name> $column The generated semantic operand
     * @param ErisGenerator<QualifiedName|null> $table The generated semantic operand
     * @param ErisGenerator<OptionalWords> $dot The generated semantic operand
     * @return ErisGenerator<ColumnName>
     */
    public static function fromOperands(ErisGenerator $column, ErisGenerator $table, ErisGenerator $dot): ErisGenerator
    {
        return Generators::map(static fn (array $parts): ColumnName => new ColumnName(column: $parts[0], table: $parts[1], dot: $parts[2]), \Tests\Generator\Operands::tuple($column, $table, $dot));
    }

}
