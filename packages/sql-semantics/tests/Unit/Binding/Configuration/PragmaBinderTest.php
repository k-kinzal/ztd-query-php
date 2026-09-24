<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\PragmaBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PragmaBinder::class)]
#[Medium]
final class PragmaBinderTest extends TestCase
{
    public function testBindReadsAPragmaWithoutAnArgument(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA foreign_keys');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ReadPragmaStatement::class, $statement);
        self::assertSame(['foreign_keys'], $statement->name->parts);
        self::assertSame('PRAGMA "foreign_keys"', $statement->toString());
    }

    public function testBindKeepsTheSchemaPrefixAndASignedNumericArgument(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA main.cache_size = -2000');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement::class, $statement);
        self::assertSame(['main', 'cache_size'], $statement->name->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Pragma\NumericArgument::class, $statement->value);
        self::assertSame(\SqlSemantics\Model\Configuration\Pragma\Sign::Negative, $statement->value->sign);
        self::assertSame('2000', $statement->value->literal->text);
    }

    public function testBindDistinguishesTextAndIdentifierArguments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $text = $binder->bind("PRAGMA journal_mode = 'wal'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement::class, $text);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Pragma\TextArgument::class, $text->value);
        $identifier = $binder->bind('PRAGMA table_info(t)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement::class, $identifier);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Pragma\IdentifierArgument::class, $identifier->value);
        self::assertSame('t', $identifier->value->name);
        self::assertSame('PRAGMA "table_info" = "t"', $identifier->toString());
    }

    public function testBindTreatsAnUnsignedCallArgumentAsUnsigned(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA cache_size(+10)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Pragma\NumericArgument::class, $statement->value);
        self::assertSame(\SqlSemantics\Model\Configuration\Pragma\Sign::Positive, $statement->value->sign);
    }
}
