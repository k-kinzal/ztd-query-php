<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes MaintainPartitions values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions>
 */
final class MaintainPartitionsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions> */
    use Composition;

    /**
     * Composes partition rebuilds with generated table definitions and rows.
     * Partition selection is regenerated whenever its partition count shrinks.
     *
     * @return ErisGenerator<array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement, list<list<int>>, int}>
     */
    public static function withRows(GrammarRelease $release): ErisGenerator
    {
        $layouts = Generators::bind(Generators::choose(1, 8), static fn (int $count): ErisGenerator => Generators::tuple(Generators::constant($count), Generators::choose(0, $count - 1), Generators::bool()));
        $rows = Generators::bind(Generators::choose(1, 5), static fn (int $count): ErisGenerator => Generators::vector($count, Generators::choose(0, 1000)));
        return Generators::map(self::sample(...), Generators::tuple(
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            $layouts,
            $rows,
            \Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator::optional(),
            Generators::bool(),
            Generators::constant($release),
        ));
    }

    /**
     * Publishes the dependent statements for one generated partition layout.
     *
     * @param array{Name, Name, array{int, int, bool}, list<int>, MySql\Server\BinlogOption|null, bool, GrammarRelease} $parts Generated names, selection, rows, notation and release
     * @return array{AnalyzedStatement, AnalyzedStatement, AnalyzedStatement, list<list<int>>, int}
     */
    public static function sample(array $parts): array
    {
        [$name, $column, $layout, $values, $binlog, $upper, $release] = $parts;
        [$count, $selected, $all] = $layout;
        $table = new QualifiedName($name);
        $field = new MySql\Table\Column\ColumnDefinition(new MySql\Name\ColumnName($column), new MySql\Table\Column\OrdinaryColumn(new MySql\Type\Integral(MySql\Type\Kind\IntegralKind::Int)));
        $partitioning = new MySql\Partition\PartitionClause(new MySql\Partition\Method\HashMethod(false, new MySql\Name\ColumnUse($column)), new MySql\Literal\Numeral((string) $count), null, []);
        $create = new MySql\Table\CreateTable($table, [$field], partitioning: $partitioning);
        $selection = $all ? new MySql\Partition\AllPartitions() : new MySql\Partition\NamedPartitions([new Name(($upper ? 'P' : 'p') . $selected)]);
        $command = new MySql\Alter\Partition\MaintainPartitions(MySql\Alter\Partition\MaintenanceKind::Rebuild, $binlog, $selection);
        $inserted = array_map(static fn (int $value): MySql\Dml\Insert\InsertedRow => new MySql\Dml\Insert\InsertedRow([new MySql\Literal\NumberLiteral((string) $value)]), $values);
        $insert = new MySql\Dml\Insert\InsertRows(new MySql\Dml\Insert\InsertInto(false, null, false, new MySql\Dml\WriteTarget($table)), $inserted);
        $context = new AnalysisContext(new LanguageProfile($release), [new Name('pbt')], session: new MySql\Type\Resolved\Settings(MySql\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
        $publication = new Publication();
        $definition = $publication->publish($context, $create);
        $context = new AnalysisContext($context->profile, [new Name('pbt')], $definition->declarations(), session: $context->session);
        $affected = $release === GrammarRelease::MySql5651 ? count(array_filter($values, static fn (int $value): bool => $all || $value % $count === $selected)) : 0;
        $expected = [];
        foreach ($values as $value) {
            $expected[] = [$value];
        }
        return [$definition, $publication->publish($context, $insert), $publication->publish($context, new MySql\Alter\AlterTable($table, [$command])), $expected, $affected];
    }

    /**
     * @param ErisGenerator<MySql\Alter\Partition\MaintenanceKind> $kind Generated constructor operand
     * @param ErisGenerator<MySql\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<MySql\Partition\PartitionSelection> $partitions Generated constructor operand
     * @param ErisGenerator<list<MySql\Server\CheckOption>|list<MySql\Server\RepairOption>>|null $options Generated constructor operand
     * @param ErisGenerator<MySql\Server\BinlogOption|null>|null $ignoredBinlog Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $binlog, ErisGenerator $partitions, ?ErisGenerator $options = null, ?ErisGenerator $ignoredBinlog = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): MySql\Alter\Partition\MaintainPartitions {
            return new MySql\Alter\Partition\MaintainPartitions(kind: $parts[0], binlog: $parts[1], partitions: $parts[2], options: $parts[3], ignoredBinlog: $parts[4], inputSource: $parts[5]);
        }, Generators::tuple(
            $kind,
            $binlog,
            $partitions,
            $options ?? Generators::constant([]),
            $ignoredBinlog ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
