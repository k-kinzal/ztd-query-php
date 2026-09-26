<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Report\QueryEncoding
 */
#[CoversClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class QueryEncodingTest extends TestCase
{
    public function testRecordUsesTheSharedValueGraphForEntryArguments(): void
    {
        $query = new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('main', [\Deriver\Value\Term::constant(7)])]));
        $graph = new \Deriver\Report\ValueGraph();
        $record = (new \Deriver\Report\QueryEncoding())->record($query, $graph);
        self::assertSame('entrypoint', $record['scope']['mode']);
        self::assertArrayHasKey($record['scope']['entries'][0]['arguments'][0]['value'], $graph->records);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testKeyIncludesStructuralBudgetsAndEntryValues(): void
    {
        $query = new \Deriver\Api\Query\ReturnQuery('target');
        $limited = new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(transfers:1));
        $encoder = new \Deriver\Report\QueryEncoding();
        self::assertSame($encoder->key($query), $encoder->key($query));
        self::assertNotSame($encoder->key($query), $encoder->key($limited));
    }
}
