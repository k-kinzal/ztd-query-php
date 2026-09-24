<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Statement\Loading\LoadFormat;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoadFormat::class)]
#[Medium]
final class LoadFormatTest extends TestCase
{
    public function testBindingReadsTheKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD XML INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame(LoadFormat::Xml, $statement->format);
    }
}
