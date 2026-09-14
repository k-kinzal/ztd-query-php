<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\DefinitionList as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class DefinitionListTest extends TestCase
{
    public function testSplitColumnDefinitionsKeepsNestedAndQuotedCommas(): void
    {
        self::assertSame(['id INT', "label TEXT DEFAULT 'a,b'", 'amount NUMERIC(8, 2)'], (new Subject())->splitColumnDefinitions("id INT, label TEXT DEFAULT 'a,b', amount NUMERIC(8, 2)"));
    }

    public function testIsTableConstraintRecognizesNamedAndUnnamedConstraints(): void
    {
        self::assertTrue((new Subject())->isTableConstraint('CONSTRAINT pk PRIMARY KEY (id)'));
        self::assertTrue((new Subject())->isTableConstraint('FOREIGN KEY (id) REFERENCES a(id)'));
        self::assertFalse((new Subject())->isTableConstraint('id INT NOT NULL'));
    }

    public function testParseColumnsExcludesTableConstraints(): void
    {
        $columns = (new Subject())->parseColumns('id INT, name TEXT, PRIMARY KEY (id)', 'a', ['id']);
        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
    }
}
