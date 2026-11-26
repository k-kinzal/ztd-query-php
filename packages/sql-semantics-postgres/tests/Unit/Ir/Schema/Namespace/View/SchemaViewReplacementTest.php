<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewReplacement;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaViewReplacement::class)]
final class SchemaViewReplacementTest extends TestCase
{
    public function testCheckPreservesTheOldPrefixAndBindsNewFieldsToTheLatestDefinition(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze(
            'CREATE SCHEMA archive CREATE VIEW feed (n) AS VALUES (1)'
            . ' CREATE VIEW earlier AS SELECT n FROM feed'
            . ' CREATE OR REPLACE VIEW feed (n,flag) AS VALUES (2,true)'
            . ' CREATE VIEW later AS SELECT flag FROM feed',
            $semantics->context([])
        );

        self::assertInstanceOf(CreateSchema::class, $value);
        self::assertCount(4, $value->views);
        $dependent = $value->views[3]->query;
        self::assertInstanceOf(Select::class, $dependent);
        self::assertInstanceOf(TableSource::class, $dependent->source);
        self::assertSame(ColumnKind::Boolean, $dependent->source->columnKind(0));
        self::assertStringContainsString('CREATE OR REPLACE VIEW "archive"."feed" ("n", "flag")', $value->toString());
    }

    public function testCheckRejectsChangingAnEstablishedTextCollation(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema view replacement requires the same established collations.');

        (new Semantics())->analyze(
            'CREATE SCHEMA archive CREATE VIEW feed AS SELECT \'old\' COLLATE pg_catalog."C" AS caption'
            . ' CREATE OR REPLACE VIEW feed AS SELECT \'new\' AS caption'
        );
    }
}
