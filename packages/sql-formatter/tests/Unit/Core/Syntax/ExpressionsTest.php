<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\Syntax\Document;
use SqlFormatter\Core\Syntax\Expressions;

#[CoversClass(Expressions::class)]
#[CoversClass(Document::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class ExpressionsTest extends TestCase
{
    public function testMarkDistinguishesRangeAndBooleanConjunctions(): void
    {
        $document = new Document('');
        $expressions = new Expressions($document, (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        $expressions->mark('expr', 0, 4, [1 => 'BETWEEN', 3 => 'AND']);
        $expressions->mark('expr', 0, 8, [5 => 'AND']);
        self::assertSame([5 => true], $document->logical);
    }

    public function testMarkRecordsCaseAndUnarySign(): void
    {
        $document = new Document('');
        $expressions = new Expressions($document, (new \SqlFormatter\Platform\Sqlite\Dialect())->syntaxRules());
        $expressions->mark('expr', 0, 8, [0 => 'CASE', 8 => 'END']);
        $expressions->mark('expr', 2, 3, [2 => '-']);
        self::assertSame([0 => 8], $document->casePairs);
        self::assertSame([2 => true], $document->unary);
    }
}
