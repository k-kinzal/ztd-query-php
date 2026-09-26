<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(Semantics::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    public function testAnalyzeAcceptsTheDialectContract(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        self::assertSame('DROP TABLE example', $semantics->analyze('DROP TABLE example')->toString());
    }
}
