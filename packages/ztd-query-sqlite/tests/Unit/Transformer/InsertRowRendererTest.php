<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Platform\Sqlite\Transformer\InsertRowRenderer;

#[CoversClass(InsertRowRenderer::class)]
final class InsertRowRendererTest extends TestCase
{
    public function testRenderParsesSqliteDefaultAndRendersCompleteRow(): void
    {
        $renderer = new InsertRowRenderer();
        $provided = $renderer->providedExpressions(['id', 'name'], ['  default  ', "  'Ada'  "]);

        self::assertSame(['name' => "'Ada'"], $provided);
        self::assertSame([
            'id' => '8',
            'name' => "'Ada'",
            'status' => "'active'",
            'note' => 'NULL',
        ], $renderer->render(
            ['id', 'name', 'status', 'note'],
            $provided,
            ['id' => '99', 'status' => "'active'"],
            ['id' => 8],
        ));
    }

    public function testRejectsMismatchedSqliteValues(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Insert values count does not match column count.');

        (new InsertRowRenderer())->providedExpressions(['id'], []);
    }

    public function testPreservesEveryProvidedSqliteExpression(): void
    {
        self::assertSame(
            ['id' => '42', 'name' => "'Ada'"],
            (new InsertRowRenderer())->providedExpressions(['id', 'name'], ['42', "'Ada'"]),
        );
    }
    public function testProvidedExpressionsPairsEachColumnWithWhatIsWrittenForIt(): void
    {
        self::assertSame(['id' => '1'], (new InsertRowRenderer())->providedExpressions(['id'], ['1']));
    }

}
