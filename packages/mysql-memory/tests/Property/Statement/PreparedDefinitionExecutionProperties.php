<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
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
    public function satisfiesInvalidationExpandsTheGeneratedStarAgainstTheChangedTable(): void
    {
        $sequences = Generators::map(static function (array $suffixes): array {
            [$tableSuffix, $columnSuffix] = $suffixes;
            $profiles = Interpretation::all(GrammarRelease::MySql847, ParameterStyle::Native);
            $table = new QualifiedName(new Name('target_' . $tableSuffix));
            $statement = new QueryRoot(new Select([], [new Star()], new TableReference($table)));
            $alternatives = array_map(static fn (Interpretation $profile): Alternative => new Alternative($profile, $statement), $profiles);
            $first = new ColumnDefinition(new ColumnName(new Name('original')), new OrdinaryColumn(new Integral(IntegralKind::Int)));
            $added = new ColumnDefinition(new ColumnName(new Name('added_' . $columnSuffix)), new OrdinaryColumn(new Integral(IntegralKind::Int)));
            return [new CreateTable($table, [$first]), new Definition($profiles[0], new Name('pbt'), $alternatives), new AlterTable($table, [new AddColumn($added)])];
        }, Generators::tuple(Generators::nat(), Generators::nat()));

        $this->forAll($sequences)->then(static function (array $sequence): void {
            [$create, $input, $alter] = $sequence;
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
            $binding = new Binding();
            $execution = new Execution($session);
            $execution->statement($binding->statement($session, $create));
            $bound = $binding->statement($session, $initial->statement, profile: $initial->interpretation->language);
            $cache = PreparedCache::capture($bound, $session, $input);
            $parameters = ParameterBindings::capture($bound);
            $before = $cache->definition->execute($session, 'generated preparation', []);
            self::assertInstanceOf(ResultSet::class, $before);
            self::assertCount(1, $before->columns);

            $execution->statement($binding->statement($session, $alter));
            self::assertTrue($cache->changed($session));
            $cache->refresh($session, $parameters, []);
            $after = $cache->definition->execute($session, 'generated preparation', []);
            self::assertInstanceOf(ResultSet::class, $after);
            self::assertCount(2, $after->columns);
            self::assertSame($alter->commands[0]->column->name->column->value, $after->columns[1]->name);
            self::assertFalse($cache->changed($session));
        });
    }
}
