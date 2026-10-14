<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\DatabaseOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(DatabaseOptions::class)]
#[Medium]
final class DatabaseOptionsTest extends TestCase
{
    public function testDerivePreservesUnknownOptionNamesAsOrderedFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE DATABASE d COLLATE 'bad_collation' CHARACTER SET bad_charset");

        self::assertEquals([new UnknownCollation('bad_collation'), new UnknownCharset('bad_charset')], $operation->facts->diagnostics);
        self::assertEquals([new ParseFailure(new UnknownCollation('bad_collation'), true), new ParseFailure(new UnknownCharset('bad_charset'), true)], $operation->facts->warnings);
    }

    public function testDeriveChecksAvailabilityUnderTheSelectedRelease(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER DATABASE d COLLATE utf8mb4_0900_ai_ci');

        self::assertEquals([new UnknownCollation('utf8mb4_0900_ai_ci')], $operation->facts->diagnostics);
    }
}
