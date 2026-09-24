<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Utility\Transfer\CopyOptionList;
use SqlSemantics\Dialect;

#[CoversClass(CopyOptionList::class)]
#[Medium]
final class CopyOptionListTest extends TestCase
{
    public function testReadKeepsTheServerOrder(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("COPY BINARY t FROM STDIN USING DELIMITERS '|' FREEZE"), ['CopyStmt'])[0];
        $options = CopyOptionList::read($source, new Identifiers(Dialect::PostgreSql));
        self::assertSame([['format', 'binary'], ['delimiter', '|'], ['freeze', null]], array_map(static fn (array $option): array => [$option[0], $option[1]], $options));
    }

    public function testLegacyReadsKeywordOptions(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("COPY t TO STDOUT CSV QUOTE AS '''' FORCE QUOTE a, \"B\" FORCE NULL * ENCODING 'UTF8'"), ['CopyStmt'])[0];
        $items = Tree::outer($source, ['copy_opt_item']);
        $identifiers = new Identifiers(Dialect::PostgreSql);
        self::assertSame(['quote', "'"], array_slice(CopyOptionList::legacy($items[1], $identifiers), 0, 2));
        self::assertSame(['force_quote', ['a', 'B']], array_slice(CopyOptionList::legacy($items[2], $identifiers), 0, 2));
        self::assertSame(['force_null', true], array_slice(CopyOptionList::legacy($items[3], $identifiers), 0, 2));
    }

    public function testGenericReadsEveryArgumentShape(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COPY t TO STDOUT (Format csv, force_quote (a, "B"), force_null *, log_verbosity DEFAULT, header, x 3)'), ['CopyStmt'])[0];
        $identifiers = new Identifiers(Dialect::PostgreSql);
        $values = array_map(static fn (\SqlParser\Parser\Node $item): array => array_slice(CopyOptionList::generic($item, $identifiers), 0, 2), Tree::outer($source, ['copy_generic_opt_elem']));
        self::assertSame([['format', 'csv'], ['force_quote', ['a', 'B']], ['force_null', true], ['log_verbosity', 'default'], ['header', null], ['x', 3]], $values);
    }

    public function testTextDecodesTheConstant(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("COPY t TO STDOUT NULL E'\\\\t'"), ['copy_opt_item'])[0];
        self::assertSame('\\t', CopyOptionList::text($source, new Identifiers(Dialect::PostgreSql)));
    }

    public function testNamesReadsTheColumnList(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COPY t (a, "B") TO STDOUT'), ['columnList'])[0];
        self::assertSame(['a', 'B'], CopyOptionList::names($source, new Identifiers(Dialect::PostgreSql)));
    }

    /**
     * @param list<array{string, mixed}> $expected
     */
    #[TestWith(["COPY t FROM STDIN binary csv header delimiter ',' null 'x' escape 'e' encoding 'utf8' force not null a force quote * force null b freeze", [['format', 'binary'], ['format', 'csv'], ['header', null], ['delimiter', ','], ['null', 'x'], ['escape', 'e'], ['encoding', 'utf8'], ['force_not_null', ['a']], ['force_quote', true], ['force_null', ['b']], ['freeze', null]]])]
    #[TestWith(['COPY t TO STDOUT', []])]
    #[TestWith(['COPY t TO STDOUT (format csv)', [['format', 'csv']]])]
    public function testReadReadsEveryLegacyKeyword(string $sql, array $expected): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse($sql), ['CopyStmt'])[0];
        self::assertSame($expected, array_map(static fn (array $option): array => [$option[0], $option[1]], CopyOptionList::read($source, new Identifiers(Dialect::PostgreSql))));
    }
}
