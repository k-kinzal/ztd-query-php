<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Option;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Routine\RoutineInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineInvariant::class)]
#[Medium]
final class RoutineInvariantTest extends TestCase
{
    public function testDefinitionAppliesTheProcedureAttributeRules(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        RoutineInvariant::definition($origin, new QualifiedName(['f']), [], [Option\Volatility::Stable], false);
        $this->expectException(InvalidStructure::class);
        RoutineInvariant::definition($origin, new QualifiedName(['f']), [], [Option\Volatility::Stable], true);
    }

    public function testDefinitionRejectsAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        RoutineInvariant::definition((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin, new QualifiedName(['f']), [], [], false);
    }
}
