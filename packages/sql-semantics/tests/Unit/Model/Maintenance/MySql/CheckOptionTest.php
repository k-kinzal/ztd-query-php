<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\CheckOption;
use SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CheckOption::class)]
#[Medium]
final class CheckOptionTest extends TestCase
{
    #[TestWith(['QUICK', CheckOption::Quick])]
    #[TestWith(['FAST', CheckOption::Fast])]
    #[TestWith(['MEDIUM', CheckOption::Medium])]
    #[TestWith(['EXTENDED', CheckOption::Extended])]
    #[TestWith(['CHANGED', CheckOption::Changed])]
    #[TestWith(['FOR UPGRADE', CheckOption::Upgrade])]
    public function testClassifiesTheOperationSpecificSelection(string $suffix, CheckOption $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CHECK TABLE t ' . $suffix);
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        self::assertSame($expected, $statement->options[0]);
    }
}
