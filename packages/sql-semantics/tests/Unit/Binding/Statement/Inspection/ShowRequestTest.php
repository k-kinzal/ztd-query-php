<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\ShowRequest;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowRequest::class)]
#[Medium]
final class ShowRequestTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'SHOW FULL TABLES', 'show_param', 'TABLES', false])]
    #[TestWith(['mysql-5.7.44', 'SHOW FULL COLUMNS FROM t', 'show_param', 'COLUMNS', false])]
    #[TestWith(['mysql-8.0.44', 'SHOW EXTENDED FULL TABLES', 'show_tables_stmt', 'TABLES', true])]
    #[TestWith(['mysql-8.4.7', 'SHOW EXTENDED FULL COLUMNS FROM t', 'show_columns_stmt', 'COLUMNS', true])]
    public function testOfReadsModifiersBeforeTheKeywordsOnEveryRelease(string $version, string $sql, string $form, string $word, bool $extended): void
    {
        $request = ShowRequest::of((new DialectParser(Dialect::MySql, $version))->parse($sql));
        self::assertNotNull($request);
        self::assertSame($form, $request->form->name);
        self::assertTrue($request->full);
        self::assertSame($extended, $request->extended);
        self::assertSame($word, $request->words[0]);
    }

    public function testOfReturnsNullForOtherStatements(): void
    {
        $parser = new DialectParser(Dialect::MySql);
        self::assertNull(ShowRequest::of($parser->parse('SELECT 1')));
        self::assertNull(ShowRequest::of($parser->parse('SHOW ENGINES')));
    }

    public function testWordReadsAnEmptyStringPastTheEnd(): void
    {
        $request = ShowRequest::of((new DialectParser(Dialect::MySql))->parse('SHOW TABLE STATUS'));
        self::assertNotNull($request);
        self::assertFalse($request->full);
        self::assertSame('TABLE', $request->word(0));
        self::assertSame('STATUS', $request->word(1));
        self::assertSame('', $request->word(2));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Inspection\Schema\ShowTableStatusStatement::class, (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLE STATUS'));
    }
}
