<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseInvariant;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DatabaseInvariant::class)]
#[Medium]
final class DatabaseInvariantTest extends TestCase
{
    public function testTargetRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::target($origin, 'app');
    }

    public function testTargetRejectsAnEmptyName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::target($origin, '');
    }

    public function testOptionsAcceptsDistinctCreationProperties(): void
    {
        DatabaseInvariant::options([new DatabaseOption(DatabaseParameter::Locale, 'C'), new DatabaseOption(DatabaseParameter::IcuLocale, 'und')], true);
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options([new DatabaseOption(DatabaseParameter::Locale, 'C'), new DatabaseOption(DatabaseParameter::LcCtype, 'C')], true);
    }

    public function testOptionsRejectsCreationPropertiesInAnAlteration(): void
    {
        DatabaseInvariant::options([new DatabaseOption(DatabaseParameter::IsTemplate, true)], false);
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options([new DatabaseOption(DatabaseParameter::Owner, 'alice')], false);
    }

    public function testOptionsRejectsARepeatedProperty(): void
    {
        $this->expectException(InvalidStructure::class);
        DatabaseInvariant::options([new DatabaseOption(DatabaseParameter::Owner, 'a'), new DatabaseOption(DatabaseParameter::Owner, 'b')], true);
    }
}
