<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Statement\Loading\LoadSource;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoadSource::class)]
#[Medium]
final class LoadSourceTest extends TestCase
{
    public function testBindingReadsTheKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA FROM S3 'bucket/f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame(LoadSource::S3, $statement->location);
    }
}
