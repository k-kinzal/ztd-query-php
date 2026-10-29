<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Occurrences;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Composition;
use Tests\Generator\Operands;

/**
 * Composes Script values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Script>
 */
final class ScriptGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Script> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Statement>> $statements Generated constructor operand
     */
    public function __construct(ErisGenerator $statements)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Script {
            return new \SqlSemantics\Statement\Script(statements: $parts[0]);
        }, Operands::tuple(
            $statements
        ));
    }
    /**
     * Generates a declaration, its write and its read as one dependent script.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\Script>
     */
    public static function declaredTable(): ErisGenerator
    {
        return new self(\Tests\Generator\MySql\Statement\QueryRootGenerator::declaredTableSequence());
    }

    /**
     * Generates a whole table workload from primitive rows and actions. No database
     * participates in generation. The independent row model supplies exact replies;
     * publication supplies only semantic bindings, never the expected result.
     *
     * @param 'rows'|'set'|'query'|'update'|'delete'|'truncate'|'commit'|'rollback'|'view'|'index' $required A family exercised by every sample
     * @return ErisGenerator<list<array{M\Statement, list<list<string>>|int, int}>> Statement, result rows or affected count, expected SQL error (zero for success)
     */
    public static function tableChanges(GrammarRelease $release, string $required): ErisGenerator
    {
        return Operands::bind(Generators::choose(1, 4), static function (int $width) use ($release, $required): ErisGenerator {
            $row = Generators::map(self::integerRow(...), Operands::tuple(ScalarGenerator::integers(), Operands::vector($width - 1, ScalarGenerator::integers())));
            $actions = Operands::tuple(Generators::elements(['rows', 'set', 'query', 'update', 'delete', 'truncate', 'commit', 'rollback', 'view', 'index']), $row);
            return Generators::map(static fn (array $parts): array => self::tableSequence($release, $parts[0], $parts[1], array_merge([[$required, $parts[2]]], $parts[3])), Operands::tuple(
                new Identifier\NameGenerator(),
                Generators::map(self::integerRows(...), Operands::tuple($row, Operands::sized(Operands::seq($row), 4))),
                $row,
                Operands::sized(Operands::seq($actions), 8),
            ));
        });
    }

    /**
     * @param array{array{Scalar, int}, list<array{Scalar, int}>} $parts
     * @return non-empty-list<array{Scalar, int}>
     */
    public static function integerRow(array $parts): array
    {
        return [$parts[0], ...$parts[1]];
    }

    /**
     * @param array{non-empty-list<array{Scalar, int}>, list<non-empty-list<array{Scalar, int}>>} $parts
     * @return non-empty-list<non-empty-list<array{Scalar, int}>>
     */
    public static function integerRows(array $parts): array
    {
        return [$parts[0], ...$parts[1]];
    }

    /**
     * Reconstructs all names, declarations, writes, reads and expectations whenever
     * Eris shrinks any prerequisite. The execution callback receives no raw recipe.
     *
     * @param non-empty-list<non-empty-list<array{Scalar, int}>> $initial
     * @param list<array{string, non-empty-list<array{Scalar, int}>}> $actions
     * @return list<array{M\Statement, list<list<string>>|int, int}>
     */
    public static function tableSequence(GrammarRelease $release, Name $name, array $initial, array $actions): array
    {
        $target = new QualifiedName($name, new Name('pbt'));
        $columns = array_map(static fn (int $position): Name => new Name('column_' . $position), array_keys($initial[0]));
        $elements = array_map(static fn (Name $column): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int))), $columns);
        $absent = new AnalysisContext(new LanguageProfile($release), [new Name('pbt')], [], true);
        $publication = new Publication();
        $create = $publication->publish($absent, new M\Table\CreateTable($target, $elements));
        $declared = new AnalysisContext($absent->profile, $absent->searchPath, $create->declarations(), true);
        $sequence = [[$publication->publish($absent, self::tableRead($target, $columns)), 0, 1146], [$create, 0, 0]];
        $rows = array_map(static fn (array $row): array => array_map(static fn (array $value): int => $value[1], $row), $initial);
        $insert = new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($target)), array_map(static fn (array $row): M\Dml\Insert\InsertedRow => new M\Dml\Insert\InsertedRow(array_map(static fn (array $value): Scalar => $value[0], $row)), $initial));
        $sequence[] = [$publication->publish($declared, $insert), count($initial), 0];
        $sequence[] = [$publication->publish($declared, self::tableRead($target, $columns)), self::orderedRows($rows), 0];
        foreach ($actions as [$verb, $values]) {
            [$requests, $rows] = self::tableAction($release, $verb, $target, $columns, $values, $rows);
            $context = $declared;
            foreach ($requests as [$request, $affected]) {
                $published = $publication->publish($context, Occurrences::fresh($request));
                $sequence[] = [$published, $affected, 0];
                if ($published->declarations() !== []) {
                    $context = new AnalysisContext($declared->profile, $declared->searchPath, [...$declared->tables, ...$published->declarations()], true);
                }
            }
            $sequence[] = [$publication->publish($declared, self::tableRead($target, $columns)), self::orderedRows($rows), 0];
        }
        $sequence[] = [$publication->publish($declared, new M\Alter\DropTable(false, false, [new M\Alter\TargetTable($target)])), 0, 0];
        $sequence[] = [$publication->publish($declared, self::tableRead($target, $columns)), 0, 1146];
        return $sequence;
    }

    /**
     * @param non-empty-list<Name> $columns
     * @param non-empty-list<array{Scalar, int}> $values
     * @param list<list<int>> $rows
     * @return array{list<array{M\Statement, int|list<list<string>>}>, list<list<int>>}
     */
    public static function tableAction(GrammarRelease $release, string $verb, QualifiedName $target, array $columns, array $values, array $rows): array
    {
        $expressions = array_map(static fn (array $value): Scalar => $value[0], $values);
        $numbers = array_map(static fn (array $value): int => $value[1], $values);
        $into = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($target));
        if ($verb === 'index') {
            $name = new Name('index_' . count($columns));
            $parts = array_map(static fn (Name $column): M\Table\Key\ColumnPart => new M\Table\Key\ColumnPart($column), $columns);
            return [[[new M\Table\CreateIndex($name, $target, $parts), 0], [new M\Alter\DropIndex($name, $target), 0]], $rows];
        }
        if (in_array($verb, ['rows', 'set', 'query'], true)) {
            $request = match ($verb) {
                'rows' => new M\Dml\Insert\InsertRows($into, [new M\Dml\Insert\InsertedRow($expressions)]),
                'set' => new M\Dml\Insert\InsertSet($into, array_map(static fn (Name $column, Scalar $value): M\Dml\Assignment => new M\Dml\Assignment(new M\Name\ColumnUse($column), $value), $columns, $expressions)),
                'query' => new M\Dml\Insert\InsertQuery($into, new M\Query\Select([], array_map(static fn (Scalar $value): M\Query\SelectExpression => new M\Query\SelectExpression($value), $expressions))),
            };
            return [[[$request, 1]], [...$rows, $numbers]];
        }
        if ($verb === 'truncate') {
            return [[[new M\Alter\TruncateTable($target), 0]], []];
        }
        $predicate = new M\Expression\Comparison(M\Expression\ComparisonOperator::Less, new M\Name\ColumnUse($columns[0]), Occurrences::fresh($expressions[0]));
        if ($verb === 'view') {
            $name = new QualifiedName(new Name('view_' . $target->name->value), $target->schema);
            $projection = array_map(static fn (Name $column): M\Query\SelectExpression => new M\Query\SelectExpression(new M\Name\ColumnUse($column)), $columns);
            $create = new M\View\CreateView(new M\View\ViewDefinition($name, new M\Query\Select([], $projection, new M\Relation\TableReference($target))));
            $alter = new M\View\AlterView(new M\View\ViewDefinition($name, new M\Query\Select([], $projection, new M\Relation\TableReference($target), where: $predicate)));
            $filtered = array_values(array_filter($rows, static fn (array $row): bool => $row[0] < $numbers[0]));
            return [[[$create, 0], [self::tableRead($name, $columns), self::orderedRows($rows)], [$alter, 0], [self::tableRead($name, $columns), self::orderedRows($filtered)], [new M\View\DropView([$name]), 0]], $rows];
        }
        if ($verb === 'delete') {
            $retained = array_values(array_filter($rows, static fn (array $row): bool => $row[0] >= $numbers[0]));
            return [[[new M\Dml\Delete(null, [], new M\Dml\WriteTarget($target), $predicate), count($rows) - count($retained)]], $retained];
        }
        $changed = array_map(static fn (array $row): array => $row[0] < $numbers[0] ? $numbers : $row, $rows);
        $affected = count(array_filter($rows, static fn (array $row): bool => $row[0] < $numbers[0]));
        $update = new M\Dml\Update(null, false, false, [new M\Relation\TableReference($target)], array_map(static fn (Name $column, Scalar $value): M\Dml\Assignment => new M\Dml\Assignment(new M\Name\ColumnUse($column), $value), $columns, $expressions), $predicate);
        if ($verb === 'update') {
            return [[[$update, $affected]], $changed];
        }
        $profile = new LanguageProfile($release);
        $finish = $verb === 'commit' ? new M\Server\Transaction\Commit(profile: $profile) : new M\Server\Transaction\Rollback(profile: $profile);
        return [[[new M\Server\Transaction\Begin(profile: $profile), 0], [$update, $affected], [$finish, 0]], $verb === 'commit' ? $changed : $rows];
    }

    /**
     * @param non-empty-list<Name> $columns
     */
    public static function tableRead(QualifiedName $table, array $columns): M\QueryRoot
    {
        return new M\QueryRoot(new M\Query\Select([], array_map(static fn (Name $column): M\Query\SelectExpression => new M\Query\SelectExpression(new M\Name\ColumnUse($column)), $columns), new M\Relation\TableReference($table), orderBy: array_map(static fn (Name $column): M\Query\OrderItem => new M\Query\OrderItem(new M\Name\ColumnUse($column)), $columns)));
    }

    /**
     * @param list<list<int>> $rows
     * @return list<list<string>>
     */
    public static function orderedRows(array $rows): array
    {
        usort($rows, static fn (array $left, array $right): int => $left <=> $right);
        return array_map(static fn (array $row): array => array_map(static fn (int $value): string => (string) $value, $row), $rows);
    }

}
