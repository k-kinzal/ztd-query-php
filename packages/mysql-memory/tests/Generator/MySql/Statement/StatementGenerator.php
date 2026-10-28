<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Composition;
use Tests\Generator\Operands;

/**
 * Composes Statement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Statement>
 */
final class StatementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Statement> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<M\Statement>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Operands::oneOf($alternatives);
    }

    /**
     * Composes complete definitions before and after their generated target declaration exists.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function definitionOptions(GrammarRelease $release): ErisGenerator
    {
        return Operands::bind(new Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)), static function (M\Table\CreateTable $create) use ($release): ErisGenerator {
            $database = $create->name->schema ?? new Name('pbt');
            $profile = new \SqlSemantics\Contract\LanguageProfile($release);
            $missing = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], [], true);
            $definition = \SqlSemantics\Facade\Semantics::fromStatement($missing, $create);
            $declared = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], $definition->declarations(), true);
            return Generators::map(static fn (array $parts): array => self::definitionInput($parts, $definition, $missing, $declared), Operands::oneOf(self::definitionForms($release, $create)));
        });
    }

    /**
     * Publishes both execution contexts for a generated definition option.
     *
     * @param array{M\Statement, int} $parts The complete definition and its SQL condition
     * @return array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}
     */
    public static function definitionInput(array $parts, \SqlSemantics\Statement\AnalyzedStatement $definition, \SqlSemantics\Contract\AnalysisContext $missing, \SqlSemantics\Contract\AnalysisContext $declared): array
    {
        [$request, $expected] = $parts;
        return [$definition, \SqlSemantics\Facade\Semantics::fromStatement($missing, $request), \SqlSemantics\Facade\Semantics::fromStatement($declared, \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($request)), $expected];
    }

    /**
     * Generates option operands whose conditions precede target lookup and definition installation.
     *
     * @return non-empty-list<ErisGenerator<array{M\Statement, int}>>
     */
    public static function definitionForms(GrammarRelease $release, M\Table\CreateTable $create): array
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $value): string => 'unknown_' . $value, Operands::sized(Generators::nat(), 1000)));
        $algorithms = new Alter\Modifier\AlgorithmOptionGenerator($names);
        $locks = new Alter\Modifier\LockOptionGenerator($names);
        $engine = new Server\Storage\Option\EngineOptionGenerator(Generators::constant(new Name('InnoDB')));
        $choices = [
            Operands::tuple(Generators::map(static fn (M\Alter\Modifier\AlgorithmOption $option): M\Statement => new M\Alter\AlterTable($create->name, [$option]), $algorithms), Generators::constant(1800)),
            Operands::tuple(Generators::map(static fn (M\Alter\Modifier\LockOption $option): M\Statement => new M\Alter\AlterTable($create->name, [$option]), $locks), Generators::constant(1801)),
            Operands::tuple(new Server\Storage\CreateTablespaceGenerator($names, options: Generators::vector(2, $engine)), Generators::constant(1527)),
        ];
        if (version_compare(substr($release->value, 6), '8.0.16', '>=')) {
            $text = new Literal\TextGenerator(Generators::map(static fn (int $value): string => 'bad_' . $value, Operands::sized(Generators::nat(), 1000)));
            $encryption = new Server\Database\DatabaseEncryptionGenerator($text);
            $choices[] = Operands::tuple(new Server\Database\CreateDatabaseGenerator(Generators::bool(), $names, Generators::vector(1, $encryption)), Generators::constant(1525));
        }
        if (version_compare(substr($release->value, 6), '8.0.21', '>=')) {
            array_push($choices, ...self::attributeForms($create));
        }
        return $choices;
    }

    /**
     * Composes every JSON attribute role with the same dependent column and table names.
     *
     * @return non-empty-list<ErisGenerator<array{M\Statement, int}>>
     */
    public static function attributeForms(M\Table\CreateTable $create): array
    {
        $column = $create->elements[0];
        \SqlSemantics\Diagnostic\Check::invariant($column instanceof M\Table\Column\ColumnDefinition, 'A generated table supplies its column declaration.');
        $text = new Literal\TextGenerator(Generators::map(static fn (int $value): string => 'bad_' . $value, Operands::sized(Generators::nat(), 1000)));
        $columnAttribute = new Table\Column\EngineAttributeGenerator($text, Generators::bool());
        $columns = Generators::map(static fn (M\Table\Column\EngineAttribute $attribute): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition($column->name, new M\Table\Column\OrdinaryColumn($column->specification->dataType(), [$attribute])), $columnAttribute);
        $tableOption = new Table\Option\TextOptionGenerator(Generators::elements([M\Table\Option\Kind\TextOptionKind::EngineAttribute, M\Table\Option\Kind\TextOptionKind::SecondaryEngineAttribute]), $text);
        $indexOption = new Table\Key\Option\IndexEngineAttributeGenerator($text, Generators::bool());
        $key = new M\Table\Key\ColumnPart($column->name->column);
        $forms = [
            Generators::map(static fn (M\Table\Column\ColumnDefinition $element): M\Statement => new M\Table\CreateTable($create->name, [$element]), $columns),
            Generators::map(static fn (M\Table\Column\ColumnDefinition $element): M\Statement => new M\Alter\AlterTable($create->name, [new M\Alter\Column\AddColumn($element)]), $columns),
            Generators::map(static fn (M\Table\Option\TextOption $option): M\Statement => new M\Table\CreateTable($create->name, [$column], [$option]), $tableOption),
            Generators::map(static fn (M\Table\Option\TextOption $option): M\Statement => new M\Alter\AlterTable($create->name, [new M\Alter\Command\SetTableOptions([$option])]), $tableOption),
            Generators::map(static fn (M\Table\Key\Option\IndexEngineAttribute $option): M\Statement => new M\Table\CreateIndex(new Name('generated_index'), $create->name, [$key], options: [$option]), $indexOption),
            Generators::map(static fn (M\Table\Key\Option\IndexEngineAttribute $option): M\Statement => new M\Table\CreateTable($create->name, [$column, new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [$key], options: [$option])]), $indexOption),
            new Server\Storage\CreateTablespaceGenerator(Generators::constant($create->name->name), options: Generators::vector(1, new Server\Storage\Option\EngineAttributeOptionGenerator($text))),
        ];
        return array_map(static fn (ErisGenerator $form): ErisGenerator => Operands::tuple($form, Generators::constant(3980)), $forms);
    }

}
