<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\SequenceAttribute::class)]
#[Medium]
final class SequenceAttributeTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['INCREMENT BY', 'MINVALUE', 'MAXVALUE', 'START WITH', 'CACHE'], array_column(Relation\Identity\SequenceAttribute::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET INCREMENT BY 5 SET MINVALUE -3 SET NO MAXVALUE', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\SetColumnIdentity::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\Identity\SequenceValueChange::class, $statement->actions[0]->changes[1]);
        self::assertSame(Relation\Identity\SequenceAttribute::MinValue, $statement->actions[0]->changes[1]->attribute);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET INCREMENT BY 5 SET MINVALUE -3 SET NO MAXVALUE', $statement->toString());
    }
}
