<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Dialect;
use WeakReference;

#[CoversClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class ValueReaderTest extends TestCase
{
    public function testReadReleasesParserObjectsAfterLowering(): void
    {
        $parser = Dialect::Sqlite->platform()->parser();
        $tree = $parser->parse('SELECT 123');
        $treeReference = WeakReference::create($tree);
        $tokenReference = WeakReference::create($tree->tokens()[1]);
        $value = \SqlSemantics\Core\Analysis\ValueReader::forVersion($parser->version())->read($tree);
        unset($tree);
        self::assertNull($treeReference->get());
        self::assertNull($tokenReference->get());
        self::assertSame('SELECT 123', (new \SqlSemantics\Statement\Statement($value))->toString());
    }

    public function testForVersionLoadsTheResolvedLanguage(): void
    {
        $parser = Dialect::PostgreSql->platform()->parser();
        $value = \SqlSemantics\Core\Analysis\ValueReader::forVersion($parser->version())->read($parser->parse('VALUES (42)'));
        self::assertSame('VALUES( 42 )', (new \SqlSemantics\Statement\Statement($value))->toString());
    }
}
