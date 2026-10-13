<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Validation\ValueSchema;
use stdClass;

#[CoversClass(ValueSchema::class)]
#[Small]
final class ValueSchemaTest extends TestCase
{
    public function testFieldsDescribesEveryRetainedRootField(): void
    {
        self::assertSame(['context', 'facts', 'sources', 'statement'], array_keys((new ValueSchema())->fields(Operation::class)));
    }

    public function testFieldsRefusesInheritedHiddenState(): void
    {
        $this->expectExceptionMessage('Semantic value classes do not inherit hidden state.');

        (new ValueSchema())->fields(\SqlSemantics\Diagnostic\InvalidConstruction::class);
    }

    public function testCheckRefusesAnUnregisteredClass(): void
    {
        $this->expectExceptionMessage('Semantic value class stdClass has no retained-field schema.');

        (new ValueSchema())->check(stdClass::class);
    }

    public function testSchemaMatchesTheRootWithoutSqlOrParserStorage(): void
    {
        $audit = new ValueSchema();
        $audit->check(Operation::class);

        self::assertSame($audit->fields(Operation::class), $audit->schema($audit->directory(Operation::class))[Operation::class]);
    }

    public function testDirectoryFindsThePackageOfADialectValue(): void
    {
        $class = \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral::class;
        $audit = new ValueSchema();

        self::assertArrayHasKey($class, $audit->schema($audit->directory($class)));
    }
}
