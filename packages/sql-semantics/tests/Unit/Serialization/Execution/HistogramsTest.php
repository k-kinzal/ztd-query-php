<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\MySql\ImportHistogramStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\Histograms;

#[CoversClass(Histograms::class)]
#[Medium]
final class HistogramsTest extends TestCase
{
    public function testWriteKeepsImportedDataAsOneTextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind("ANALYZE TABLE t UPDATE HISTOGRAM ON id USING DATA 'a''b; DROP TABLE t'");
        self::assertInstanceOf(ImportHistogramStatement::class, $statement);
        self::assertSame("ANALYZE TABLE `t` UPDATE HISTOGRAM ON `id` USING DATA 'a''b; DROP TABLE t'", Histograms::write($statement)->toString());
    }
}
