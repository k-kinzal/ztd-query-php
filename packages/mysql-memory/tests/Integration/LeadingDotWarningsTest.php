<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Medium]
final class LeadingDotWarningsTest extends TestCase
{
    #[DataProvider('providerReleases')]
    public function testDirectFlushReturnsItsTableSpellingWarnings(string $version, int $warnings): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $statement = new M\Server\Flush\FlushTables(null, [new M\Server\Maintenance\MaintainedTable(new QualifiedName(new Name('t')), dot: M\Expression\OptionalWords::Written), new M\Server\Maintenance\MaintainedTable(new QualifiedName(new Name('u')), dot: M\Expression\OptionalWords::Written)]);
        $statement = (new \MySqlMemory\Session\Statement\Binding())->statement($session, $statement);
        $session->text = '';
        $session->dots = [];
        $reply = (new Execution($session))->statement($statement);

        self::assertSame($warnings, $reply->warnings());
        self::assertCount($warnings, $session->diagnostics->conditions);
    }

    #[DataProvider('providerReleases')]
    public function testDirectReferencesReportTheirOwnSpellingWarnings(string $version, int $warnings): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $session->query('CREATE TABLE t(i INT)');
        $statement = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('i'), new QualifiedName(new Name('t')), M\Expression\OptionalWords::Written))], new M\Relation\TableReference(new QualifiedName(new Name('t')), dot: M\Expression\OptionalWords::Written)));
        $statement = (new \MySqlMemory\Session\Statement\Binding())->statement($session, $statement);
        $session->text = '';
        $session->dots = [];
        (new Execution($session))->statement($statement);
        self::assertCount($warnings, $session->diagnostics->conditions);
        self::assertSame(array_slice([
            ['Warning', 1287, M\Notice\Deprecated::DotColumn->value],
            ['Warning', 1287, M\Notice\Deprecated::DotTable->value],
        ], 0, $warnings), $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.6' => ['5.6.51', 0];
        yield '5.7' => ['5.7.44', 2];
    }
}
