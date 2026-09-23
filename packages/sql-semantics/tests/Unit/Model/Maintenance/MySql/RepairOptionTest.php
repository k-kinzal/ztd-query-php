<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\RepairOption;
use SqlSemantics\Model\Statement\Maintenance\MySql\RepairTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RepairOption::class)]
#[Medium]
final class RepairOptionTest extends TestCase
{
    #[TestWith(['QUICK', RepairOption::Quick])]
    #[TestWith(['EXTENDED', RepairOption::Extended])]
    #[TestWith(['USE_FRM', RepairOption::UseDefinitionFile])]
    public function testClassifiesTheOperationSpecificSelection(string $suffix, RepairOption $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('REPAIR TABLE t ' . $suffix);
        self::assertInstanceOf(RepairTablesStatement::class, $statement);
        self::assertSame($expected, $statement->options[0]);
    }
}
