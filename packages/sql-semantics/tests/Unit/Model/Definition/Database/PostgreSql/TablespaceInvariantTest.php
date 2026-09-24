<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\TablespaceInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace\SetTablespaceOptionsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\ImpliedSetting;
use SqlSemantics\Schema\Storage\Parameter;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TablespaceInvariant::class)]
#[Medium]
final class TablespaceInvariantTest extends TestCase
{
    public function testParametersAcceptsKnownOverridesAndRejectsImpliedValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TABLESPACE t SET (seq_page_cost = 2, maintenance_io_concurrency = '10')");
        self::assertInstanceOf(SetTablespaceOptionsStatement::class, $statement);
        TablespaceInvariant::parameters($statement->parameters);
        $this->expectException(InvalidStructure::class);
        TablespaceInvariant::parameters([new Parameter(new QualifiedName(['seq_page_cost']), ImpliedSetting::Enabled)]);
    }

    public function testNamesRejectsMoreThanANamespaceAndAName(): void
    {
        TablespaceInvariant::names([new QualifiedName(['a', 'b'])]);
        $this->expectException(InvalidStructure::class);
        TablespaceInvariant::names([new QualifiedName(['a', 'b', 'c'])]);
    }
}
