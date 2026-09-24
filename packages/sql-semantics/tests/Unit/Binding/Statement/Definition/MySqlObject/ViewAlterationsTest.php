<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\ViewAlterations;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ViewAlterations::class)]
#[Medium]
final class ViewAlterationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsThePropertiesAndQueryInEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('ALTER ALGORITHM = MERGE DEFINER = CURRENT_USER SQL SECURITY INVOKER VIEW d.v (a, b) AS SELECT 1, 2 WITH LOCAL CHECK OPTION');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame([['d', 'v'], ['a', 'b'], ViewCheck::Local, ViewAlgorithm::Merge, ViewSecurity::Invoker, 2], [$statement->name->parts, $statement->columns, $statement->check, $statement->properties->algorithm, $statement->properties->security, count($statement->query->resultColumns())]);
        self::assertInstanceOf(CurrentAccount::class, $statement->properties->definer);
        $expected = 'ALTER ALGORITHM = MERGE DEFINER = CURRENT_USER SQL SECURITY INVOKER VIEW `d`.`v`(`a`, `b`) AS SELECT 1, 2 WITH LOCAL CHECK OPTION';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindReturnsNullForAnotherAlteration(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $statement = (new Binder($schema))->bind('ALTER TABLESPACE ts RENAME TO t2');
        self::assertNull(ViewAlterations::bind($statement->origin, $statement->source, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), ''))));
    }

    #[TestWith(['ALTER VIEW v AS SELECT 1', ViewCheck::None])]
    #[TestWith(['ALTER VIEW v AS SELECT 1 WITH CHECK OPTION', ViewCheck::Cascaded])]
    #[TestWith(['ALTER VIEW v AS SELECT 1 WITH CASCADED CHECK OPTION', ViewCheck::Cascaded])]
    #[TestWith(['ALTER VIEW v AS SELECT 1 WITH LOCAL CHECK OPTION', ViewCheck::Local])]
    public function testCheckReadsTheCheckOption(string $sql, ViewCheck $check): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertSame($check, ViewAlterations::check($statement->source));
    }
}
