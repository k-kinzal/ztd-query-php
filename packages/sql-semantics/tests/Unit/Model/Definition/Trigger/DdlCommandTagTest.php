<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\DdlCommandTag;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DdlCommandTag::class)]
#[Medium]
final class DdlCommandTagTest extends TestCase
{
    #[TestWith([DdlCommandTag::CreateTableAs])]
    #[TestWith([DdlCommandTag::AlterDefaultPrivileges])]
    #[TestWith([DdlCommandTag::SelectInto])]
    #[TestWith([DdlCommandTag::Comment])]
    public function testATagSurvivesBindingAndSerialization(DdlCommandTag $tag): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE EVENT TRIGGER x ON ddl_command_end WHEN TAG IN ('" . strtolower($tag->value) . "') EXECUTE FUNCTION f()");
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame([$tag], $statement->tags);
        self::assertStringContainsString("'" . $tag->value . "'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['SELECT'])]
    #[TestWith(['CREATE DATABASE'])]
    public function testTagsWithoutEventTriggerSupportAreNotCommandTags(string $tag): void
    {
        self::assertNull(DdlCommandTag::tryFrom($tag));
    }
}
