<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Composition;

/**
 * Composes DeclaredName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName>
 */
final class DeclaredNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\DeclaredName> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<M\Type\DeclaredName>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = new TypeNameGenerator($alternatives);
    }
    /**
     * Composes a synonym column and its canonical counterpart with a dependent write and read.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement}>
     */
    public static function synonymWrites(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $characters = \Tests\Generator\Operands::bind(new Keyword\CharacterAliasGenerator(), static fn (M\Type\Keyword\CharacterAlias $alias): ErisGenerator => new CharacterGenerator(
            Generators::constant($alias->kind()),
            Generators::constant(in_array($alias->kind(), [M\Type\Kind\CharacterKind::Char, M\Type\Kind\CharacterKind::VarChar, M\Type\Kind\CharacterKind::CharVarying], true) ? '12' : null),
            Generators::constant($alias->national()),
            alias: Generators::constant($alias),
        ));
        $types = new self([
            new IntegralGenerator(new Kind\IntegralKindGenerator()),
            new FloatingGenerator(new Kind\FloatingKindGenerator()),
            new DecimalGenerator(Generators::constant('10'), Generators::constant('2')),
            new ElementaryGenerator(Generators::constant(M\Type\Kind\ElementaryKind::Boolean)),
            new SpatialGenerator(Generators::constant(M\Type\Kind\SpatialKind::GeometryCollection), alias: $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $release === \SqlSemantics\Contract\GrammarRelease::MySql5744 ? Generators::constant(null) : Keyword\SpatialAliasGenerator::forKind(M\Type\Kind\SpatialKind::GeometryCollection)),
            $characters,
        ]);
        $tables = Generators::map(static function (M\Type\DeclaredName $type): M\Table\CreateTable {
            $canonical = match (true) {
                $type instanceof M\Type\Integral => new M\Type\Integral($type->kind, $type->width, $type->modifiers),
                $type instanceof M\Type\Floating => new M\Type\Floating($type->kind, $type->precision, $type->scale, $type->modifiers),
                $type instanceof M\Type\Decimal => new M\Type\Decimal($type->precision, $type->scale, $type->modifiers),
                $type instanceof M\Type\Elementary => new M\Type\Elementary($type->kind, $type->length),
                $type instanceof M\Type\Spatial => new M\Type\Spatial($type->kind),
                $type instanceof M\Type\Character => new M\Type\Character($type->kind, $type->length, $type->national, $type->charset),
                default => throw new LogicException('A paired declaration is generated from the synonym-bearing type families.'),
            };
            return new M\Table\CreateTable(new QualifiedName(new Name('type_pair')), [
                new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('selected')), new M\Table\Column\OrdinaryColumn($type)),
                new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('canonical')), new M\Table\Column\OrdinaryColumn($canonical)),
            ]);
        }, $types);
        return \Tests\Generator\Operands::bind(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $tables), static function (\SqlSemantics\Statement\AnalyzedStatement $definition) use ($release): ErisGenerator {
            \SqlSemantics\Diagnostic\Check::invariant($definition instanceof M\Table\CreateTable, 'The dependent write names a generated table.');
            $column = $definition->elements[0];
            \SqlSemantics\Diagnostic\Check::invariant($column instanceof M\Table\Column\ColumnDefinition, 'The paired table starts with a column.');
            $type = $column->specification->dataType();
            $writes = Generators::map(static function (int $value) use ($definition, $type): M\Dml\Insert\InsertRows {
                $operand = $type instanceof M\Type\Spatial ? new M\Literal\NullLiteral() : new M\Literal\NumberLiteral((string) $value);
                return new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($definition->name)), [new M\Dml\Insert\InsertedRow([$operand, $type instanceof M\Type\Spatial ? new M\Literal\NullLiteral() : new M\Literal\NumberLiteral((string) $value)])]);
            }, Generators::choose(0, 100));
            $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value))->context($definition->declarations(), true, new \SqlSemantics\Contract\SearchPath('pbt'));
            $query = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('selected'))), new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('canonical')))], new M\Relation\TableReference($definition->name)));
            return \Tests\Generator\Operands::tuple(
                Generators::constant($definition),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $writes, Generators::constant($context)),
                new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, Generators::constant($query), Generators::constant($context)),
            );
        });
    }

}
