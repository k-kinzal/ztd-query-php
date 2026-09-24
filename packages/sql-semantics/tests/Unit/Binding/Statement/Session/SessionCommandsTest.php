<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Analysis\Diagnostics;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Session\SessionCommands;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SessionCommands::class)]
#[Medium]
final class SessionCommandsTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\BoundStatement> $class
     */
    #[TestWith([Dialect::MySql, 'DO 1', \SqlSemantics\Model\Statement\Execution\DoExpressionsStatement::class])]
    #[TestWith([Dialect::MySql, 'KILL QUERY 7', \SqlSemantics\Model\Statement\Server\KillQueryStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DEALLOCATE ALL', \SqlSemantics\Model\Statement\Prepared\DeallocateAllStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'CLOSE ALL', \SqlSemantics\Model\Statement\Cursor\CloseAllCursorsStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'EXPLAIN SELECT 1', \SqlSemantics\Model\Statement\Plan\ExplainStatement::class])]
    #[TestWith([Dialect::MySql, "XA COMMIT 'x'", \SqlSemantics\Model\Statement\Transaction\Xa\XaCommitStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'COMMIT', \SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement::class])]
    #[TestWith([Dialect::Sqlite, 'VACUUM', \SqlSemantics\Model\Statement\Maintenance\VacuumDatabaseStatement::class])]
    public function testBindRoutesSessionAndServerCommands(Dialect $dialect, string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($dialect, $statement->origin->dialect);
    }

    public function testBindLeavesQueriesToOtherBinders(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('SELECT 1');
        $context = new QueryContext(new TableResolver($schema, new Identifiers(Dialect::PostgreSql), $schema->defaultSchema, new Diagnostics()));
        self::assertNull(SessionCommands::bind(new Origin('s0', $tree, Dialect::PostgreSql), $tree, $context));
    }
}
