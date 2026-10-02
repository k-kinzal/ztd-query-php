<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Rendering as R;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(R\SqliteCaseLayout::class)]
#[Small]
final class SqliteCaseLayoutTest extends TestCase
{
    public function testStartKeepsTheOptionalBaseDistinct(): void
    {
        $layout = new R\SqliteCaseLayout('case', 'end', '/*base*/', "\n");
        self::assertSame('case', $layout->start(null));
        self::assertSame('case/*base*/1', $layout->start('1'));
    }

    public function testFinishPreservesTheConstructedBodyAndSeparatesEnd(): void
    {
        $layout = new R\SqliteCaseLayout('case', 'end', '', '');
        $sql = $layout->finish('case when 1 then 2');
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $sql);
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(2, $result->fetchColumn());
        self::assertSame('case when 1 then 2 end', $sql);
    }

    public function testRejectsAnotherOperationInADelimiter(): void
    {
        $this->expectException(InvalidConstruction::class);
        new R\SqliteCaseLayout('CASE WHEN');
    }

    public function testRejectsAnExpressionHiddenInATriviaGap(): void
    {
        $this->expectException(InvalidConstruction::class);
        new R\SqliteCaseLayout(baseGap: ' 1 ');
    }
}
