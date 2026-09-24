<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy\Endpoint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFromStatement;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyClient::class)]
#[Medium]
final class CopyClientTest extends TestCase
{
    public function testStdoutReadingNormalizesToStdin(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('COPY t FROM STDOUT');
        self::assertInstanceOf(CopyFromStatement::class, $statement);
        self::assertInstanceOf(CopyClient::class, $statement->input);
        self::assertSame('COPY "public"."t" FROM STDIN', $statement->toString());
    }
}
