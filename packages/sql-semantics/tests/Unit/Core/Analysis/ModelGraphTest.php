<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\ModelGraph::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ModelGraphTest extends TestCase
{
    public function testFingerprintIncludesMeaningAndReferenceSharing(): void
    {
        $graph = new \SqlSemantics\Core\Analysis\ModelGraph();
        $a = SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar');
        $b = SemanticCases::select(Dialect::Sqlite, 'SELECT label FROM bar');
        self::assertNotSame($graph->fingerprint($a), $graph->fingerprint($b));
        self::assertSame($graph->fingerprint($a), $graph->fingerprint(SemanticCases::select(Dialect::Sqlite, $a->toString())));
    }
    public function testInspectRejectsAParserTreeAsSemanticData(): void
    {
        $this->expectException(RuntimeException::class);
        (new \SqlSemantics\Core\Analysis\ModelGraph())->inspect((new \SqlSemantics\Core\Ast\DialectParser(Dialect::Sqlite))->parse('SELECT 1'));
    }
    public function testIsImmutableAcceptsSharedDeclarationReferences(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT a.foo, b.foo FROM bar a, bar b');
        self::assertTrue((new \SqlSemantics\Core\Analysis\ModelGraph())->isImmutable($statement));
    }
}
