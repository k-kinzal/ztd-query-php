<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Script;
use SqlSemantics\Validation\ValueSchema;
use stdClass;

#[CoversClass(ValueSchema::class)]
#[Small]
final class ValueSchemaTest extends TestCase
{
    public function testFieldsDescribesEveryRetainedRootField(): void
    {
        self::assertSame(['SqlSemantics\\Statement\\SqlStatement::$requestInformation', 'inputSource', 'statements'], array_keys((new ValueSchema())->fields(Script::class)));
    }

    public function testFieldsIncludesInheritedStatementMeaning(): void
    {
        self::assertSame('?SqlSemantics\\Statement\\StatementSemantics', (new ValueSchema())->fields(Script::class)['SqlSemantics\\Statement\\SqlStatement::$requestInformation']);
    }

    public function testCheckRefusesAnUnregisteredClass(): void
    {
        $this->expectExceptionMessage('Semantic value class stdClass has no retained-field schema.');

        (new ValueSchema())->check(stdClass::class);
    }

    public function testSchemaMatchesTheRootWithoutSqlOrParserStorage(): void
    {
        $audit = new ValueSchema();
        $audit->check(Script::class);

        self::assertSame($audit->fields(Script::class), $audit->schema($audit->directory(Script::class))[Script::class]);
    }

    public function testDirectoryFindsThePackageOfADialectValue(): void
    {
        $class = \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral::class;
        $audit = new ValueSchema();

        self::assertArrayHasKey($class, $audit->schema($audit->directory($class)));
    }
}
