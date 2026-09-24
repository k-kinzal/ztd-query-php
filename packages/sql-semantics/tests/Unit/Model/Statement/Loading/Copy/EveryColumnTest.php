<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\Copy\CopyToStatement;
use SqlSemantics\Model\Statement\Loading\Copy\EveryColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EveryColumn::class)]
#[Medium]
final class EveryColumnTest extends TestCase
{
    public function testAsteriskSelectsEveryColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t TO STDOUT (FORMAT csv, FORCE_QUOTE *)');
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertInstanceOf(EveryColumn::class, $statement->options->forceQuote);
        self::assertSame('COPY "public"."t" TO STDOUT WITH (FORMAT \'csv\', FORCE_QUOTE *)', $statement->toString());
    }
}
