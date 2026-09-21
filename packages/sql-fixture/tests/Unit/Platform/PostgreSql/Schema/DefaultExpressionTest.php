<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\DefaultExpression as Subject;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
final class DefaultExpressionTest extends TestCase
{
    #[DataProvider('providerDefaults')]
    public function testEvaluateInterpretsConstantsAndKeepsExpressions(string $declaration, int|float|bool|string|null $expected): void
    {
        $sql = "CREATE TABLE t (c {$declaration})";
        $tree = (new PostgreSqlParser())->parse($sql);

        self::assertSame($expected, (new Subject())->evaluate($tree->find('b_expr')[0], $sql));
    }

    /**
     * @return list<array{string, int|float|bool|string|null}>
     */
    public static function providerDefaults(): array
    {
        return [
            ['INT DEFAULT 42', 42],
            ['INT DEFAULT -42', -42],
            ['INT DEFAULT +7', 7],
            ['NUMERIC DEFAULT 9.99', 9.99],
            ['NUMERIC DEFAULT -9.99', -9.99],
            ["TEXT DEFAULT 'ready'", 'ready'],
            ["TEXT DEFAULT 'it''s'", "it's"],
            ["TEXT DEFAULT E'a\\nb'", "a\nb"],
            ['TEXT DEFAULT $$dollar$$', 'dollar'],
            ['BOOLEAN DEFAULT TRUE', true],
            ['BOOLEAN DEFAULT false', false],
            ['TEXT DEFAULT NULL', null],
            ["JSONB DEFAULT '{}'::jsonb", '{}'],
            ["TEXT DEFAULT 'a'::character varying", 'a'],
            ['NUMERIC DEFAULT 1::numeric(10, 2)', 1],
            ['TEXT DEFAULT NULL::text', null],
            ["TEXT DEFAULT 'a'::text::varchar", "'a'::text::varchar"],
            ['TIMESTAMP DEFAULT now()', 'now()'],
            ['TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['UUID DEFAULT gen_random_uuid()', 'gen_random_uuid()'],
            ['INT DEFAULT (1 + 2)', '(1 + 2)'],
            ['INT DEFAULT 1 + 2', '1 + 2'],
            ["INT DEFAULT nextval('seq')", "nextval('seq')"],
            ["BYTEA DEFAULT X'00'", "X'00'"],
        ];
    }

    public function testEvaluateReturnsTheTextOfAnEmptyNode(): void
    {
        self::assertSame('', (new Subject())->evaluate(new Node('a_expr', 0, []), 'SELECT 1'));
    }

    public function testIsCastAcceptsExactlyOneTypeCastAfterTheConstant(): void
    {
        $sql = "CREATE TABLE t (c TEXT DEFAULT 'a'::text, d TEXT DEFAULT 'a' || 'b', e INT DEFAULT 1::int::bigint)";
        $expressions = (new PostgreSqlParser())->parse($sql)->find('b_expr');
        $cast = $expressions[0];
        $concat = $expressions[2];
        $doubleCast = $expressions[5];

        self::assertSame("'a'::text", $cast->text($sql));
        self::assertSame("'a' || 'b'", $concat->text($sql));
        self::assertSame('1::int::bigint', $doubleCast->text($sql));
        self::assertTrue((new Subject())->isCast($cast, array_slice($cast->tokens(), 1)));
        self::assertFalse((new Subject())->isCast($concat, array_slice($concat->tokens(), 1)));
        self::assertFalse((new Subject())->isCast($cast, []));
        self::assertFalse((new Subject())->isCast($doubleCast, array_slice($doubleCast->tokens(), 1)));
    }

    public function testIsSequenceCallRecognizesNextvalCaseInsensitively(): void
    {
        $sql = "CREATE TABLE t (a INT DEFAULT nextval('s'), b INT DEFAULT NEXTVAL('s'::regclass), c INT DEFAULT now(), d INT DEFAULT 1)";
        $expressions = (new PostgreSqlParser())->parse($sql)->find('b_expr');
        $calls = array_values(array_filter($expressions, static fn (Node $node): bool => $node->name === 'b_expr' && str_contains($node->text($sql), '(') && !str_starts_with($node->text($sql), "'")));

        self::assertTrue((new Subject())->isSequenceCall($calls[0]));
        self::assertTrue((new Subject())->isSequenceCall($calls[1]));
        self::assertFalse((new Subject())->isSequenceCall($calls[2]));
        self::assertFalse((new Subject())->isSequenceCall(new Node('a_expr', 0, [])));
    }
}
