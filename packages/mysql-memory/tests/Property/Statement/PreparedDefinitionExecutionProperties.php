<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use LogicException;
use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Exercises generated complete preparation domains through dependent DDL and execution.
 *
 * This property covers ASCII star selections on 8.4.7. It is not a generator
 * declaration claiming coverage of every Definition or statement family.
 */
#[CoversNothing]
#[Large]
final class PreparedDefinitionExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Shrinks the preparation's table name and added column together with their execution sequence.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInterpretations')]
    public function satisfiesInvalidationExpandsTheGeneratedStarAgainstTheChangedTable(bool $refused): void
    {
        $tables = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $suffix): string => 'target_' . $suffix, Generators::nat()), Generators::constant(null)));
        $queries = Generators::map(static fn (QualifiedName $table): QueryRoot => new QueryRoot(new Select([], [new Star()], new TableReference($table), where: $refused ? new \SqlSemantics\Platform\MySql\Statement\Expression\Comparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(["value'"]), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(["value'"])) : null)), $tables);
        $interpretations = new \Tests\Generator\MySql\Statement\Dml\Prepared\InterpretationGenerator(new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant(GrammarRelease::MySql847)), new \Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator(Generators::constant('utf8mb4'), Generators::constant(4)));
        $initials = new \Tests\Generator\MySql\Statement\Dml\Prepared\AlternativeGenerator($interpretations, $queries);
        $definitions = $refused ? \Tests\Generator\MySql\Statement\Dml\Prepared\DefinitionGenerator::changingEscapes($initials) : \Tests\Generator\MySql\Statement\Dml\Prepared\DefinitionGenerator::portable($initials);
        $sequences = Generators::map(static function (array $parts): array {
            [$definition, $columnSuffix] = $parts;
            $initial = $definition->select($definition->initial);
            if (!$initial->statement instanceof QueryRoot || !$initial->statement->query instanceof Select || !$initial->statement->query->from instanceof TableReference) {
                throw new LogicException('The generated preparation reads one named table.');
            }
            $table = $initial->statement->query->from->name;
            $first = new ColumnDefinition(new ColumnName(new Name('original')), new OrdinaryColumn(new Integral(IntegralKind::Int)));
            $added = new ColumnDefinition(new ColumnName(new Name('added_' . $columnSuffix)), new OrdinaryColumn(new Integral(IntegralKind::Int)));
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7', searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
            $create = \SqlSemantics\Facade\Semantics::fromStatement($semantics->context([]), new CreateTable($table, [$first]));
            $context = $semantics->context([$create]);
            return [$create, $definition, \SqlSemantics\Facade\Semantics::fromStatement($context, new AlterTable($table, [new AddColumn($added)])), \SqlSemantics\Facade\Semantics::fromStatement($context, $initial->statement)];
        }, \Tests\Generator\Operands::tuple($definitions, Generators::nat()));

        $this->forAll($sequences)->then(static function (array $sequence) use ($refused): void {
            [$create, $input, $alter, $bound] = $sequence;
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(Definition::class, $input);
            self::assertInstanceOf(AlterTable::class, $alter);
            self::assertInstanceOf(AddColumn::class, $alter->commands[0]);
            $initial = $input->select($input->initial);
            self::assertInstanceOf(QueryRoot::class, $initial->statement);
            self::assertInstanceOf(Select::class, $initial->statement->query);
            self::assertInstanceOf(TableReference::class, $initial->statement->query->from);
            self::assertSame($create->name, $initial->statement->query->from->name);
            self::assertSame($create->name, $alter->table);
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $execution = new Execution($session);
            $execution->statement($create);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $bound);
            $cache = PreparedCache::capture($bound, $session, $input);
            $parameters = ParameterBindings::capture($bound);
            $before = $cache->definition->execute($session, 'generated preparation', []);
            self::assertInstanceOf(ResultSet::class, $before);
            self::assertCount(1, $before->columns);

            $execution->statement($alter);
            self::assertTrue($cache->changed($session));
            if ($refused) {
                $mode = $session->instance->catalog->find('sql_mode');
                self::assertNotNull($mode);
                $session->variables->set($mode, 'NO_BACKSLASH_ESCAPES');
                $active = $input->select(new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation($session->semantics()->profile(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4')));
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal::class, $active->statement);
                $previous = $cache->definition;
                try {
                    $cache->refresh($session, $parameters, []);
                    self::fail('The generated interpretation carries a grammar refusal.');
                } catch (\MySqlMemory\Error\SqlError $error) {
                    self::assertSame(1064, $error->getCode());
                    self::assertStringContainsString($active->statement->near, $error->getMessage());
                    self::assertStringContainsString('at line ' . $active->statement->line, $error->getMessage());
                }
                self::assertSame($previous, $cache->definition);
                self::assertSame('pbt', $session->variables->database);
                $session->variables->set($mode, '');
            }
            $cache->refresh($session, $parameters, []);
            $after = $cache->definition->execute($session, 'generated preparation', []);
            self::assertInstanceOf(ResultSet::class, $after);
            self::assertCount(2, $after->columns);
            self::assertSame($alter->commands[0]->column->name->column->value, $after->columns[1]->name);
            self::assertFalse($cache->changed($session));
        });
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerInterpretations(): iterable
    {
        yield 'same grammar' => [false];
        yield 'escaped input refusal' => [true];
    }

}
