<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TableDropScope;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableDropScope::class)]
#[Medium]
final class TableDropScopeTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTemporaryOnlyDeletionAcrossGrammarReleases(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('DROP TEMPORARY TABLES IF EXISTS app.t,u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\DropTableStatement::class, $statement);
        self::assertSame(TableDropScope::Temporary, $statement->selection);
        self::assertSame('DROP TEMPORARY TABLE IF EXISTS `app`.`t`, `u`', $statement->toString());
    }

}
