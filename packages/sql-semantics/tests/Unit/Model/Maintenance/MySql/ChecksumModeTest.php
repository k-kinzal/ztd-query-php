<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\ChecksumMode;
use SqlSemantics\Model\Statement\Maintenance\MySql\ChecksumTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChecksumMode::class)]
#[Medium]
final class ChecksumModeTest extends TestCase
{
    #[TestWith(['', ChecksumMode::Automatic])]
    #[TestWith(['QUICK', ChecksumMode::Stored])]
    #[TestWith(['EXTENDED', ChecksumMode::Scan])]
    public function testClassifiesTheOperationSpecificSelection(string $suffix, ChecksumMode $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CHECKSUM TABLE t ' . $suffix);
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        self::assertSame($expected, $statement->mode);
    }
}
