<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsBeforeStatement;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationText::class)]
#[Medium]
final class ReplicationTextTest extends TestCase
{
    public function testCheckReturnsTheDecodedText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a''b\\nc'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        self::assertSame("a'b\nc", ReplicationText::check($statement->logName, 'name'));
        $this->expectException(InvalidStructure::class);
        ReplicationText::check($statement->logName, 'name', true);
    }

    public function testCheckRejectsANumber(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('PURGE BINARY LOGS BEFORE 1');
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $statement->moment);
        $this->expectException(InvalidStructure::class);
        ReplicationText::check($statement->moment, 'name');
    }

    public function testDecodeResolvesQuotesAndEscapes(): void
    {
        self::assertSame("it's", ReplicationText::decode("'it''s'"));
        self::assertSame("a\tb\\%", ReplicationText::decode("'a\\tb\\%'"));
        self::assertSame('say "x"', ReplicationText::decode('"say ""x"""'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerEscapes(): array
    {
        return [
            'nul' => ["'\\0'", "\0"],
            'line feed' => ["'\\n'", "\n"],
            'carriage return' => ["'\\r'", "\r"],
            'backspace' => ["'\\b'", "\x08"],
            'tab' => ["'\\t'", "\t"],
            'control z' => ["'\\Z'", "\x1a"],
            'percent' => ["'\\%'", '\\%'],
            'underscore' => ["'\\_'", '\\_'],
            'other' => ["'\\q'", 'q'],
            'escaped quote' => ["'a\\'b'", "a'b"],
            'trailing backslash' => ["'a\\'", 'a\\'],
            'escape before last' => ["'\\nx'", "\nx"],
            'doubled double quote' => ['"a""b"', 'a"b'],
        ];
    }

    #[DataProvider('providerEscapes')]
    public function testDecodeResolvesEachEscape(string $spelling, string $expected): void
    {
        self::assertSame($expected, ReplicationText::decode($spelling));
    }

    public function testCheckNamesTheOperandOfALineFeed(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a\\nb'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('LOG NAME cannot contain a line feed.');
        ReplicationText::check($statement->logName, 'LOG NAME', true);
    }

    public function testCheckRejectsAPostgreSqlString(): void
    {
        $literal = Expression::literal('x', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('LOG NAME requires a quoted MySQL string literal.');
        ReplicationText::check($literal, 'LOG NAME');
    }

    public function testCheckRejectsANationalString(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT N'x'");
        self::assertInstanceOf(BoundSelect::class, $query);
        $literal = $query->outputs[0]->expression;
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('LOG NAME requires a quoted MySQL string literal.');
        ReplicationText::check($literal, 'LOG NAME');
    }
}
