<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\MaintenanceReader;
use SqlSemantics\Statement\Maintenance\Analyze;
use SqlSemantics\Statement\Maintenance\Reindex;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(MaintenanceReader::class)]
#[Medium]
final class MaintenanceReaderTest extends TestCase
{
    #[TestWith(['ANALYZE', Analyze::class])]
    #[TestWith(['ANALYZE main', Analyze::class])]
    #[TestWith(['ANALYZE main.users', Analyze::class])]
    #[TestWith(['REINDEX', Reindex::class])]
    #[TestWith(['REINDEX user_id', Reindex::class])]
    #[TestWith(['REINDEX main.user_id', Reindex::class])]
    public function testReadReconstructsTheDistinctMaintenanceOperation(string $sql, string $class): void
    {
        $parser = new SqliteParser();
        $reader = new MaintenanceReader();
        $operation = $reader->read($parser->parse($sql)->find('cmd')[0]);
        self::assertSame($class, $operation::class);
        self::assertSame($sql, $operation->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($operation));
        self::assertSame((new SemanticGraph())->fingerprint($operation), (new SemanticGraph())->fingerprint($reader->read($parser->parse($operation->toString())->find('cmd')[0])));
    }

    public function testReadPreservesTheTargetNamespace(): void
    {
        $operation = (new MaintenanceReader())->read((new SqliteParser())->parse('REINDEX temp."order index"')->find('cmd')[0]);
        self::assertSame('temp', $operation->target?->schema?->value);
        self::assertSame('order index', $operation->target->name->value);
    }
}
