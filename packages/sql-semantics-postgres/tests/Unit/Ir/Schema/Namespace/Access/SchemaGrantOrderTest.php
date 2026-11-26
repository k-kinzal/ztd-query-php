<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Access\SchemaGrantOrder;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaGrantOrder::class)]
final class SchemaGrantOrderTest extends TestCase
{
    public function testCheckRequiresDirectLocalTargetsToBeDeclaredInTheSchema(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE archive.feed']);
        $grant = $semantics->analyze('GRANT SELECT ON SEQUENCE archive.feed TO CURRENT_USER', $context);
        self::assertInstanceOf(GrantSequenceAccess::class, $grant);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema grant requires a preceding sequence at each named local target.');

        new CreateSchema(new Identifier('archive'), grants: [$grant]);
    }

    public function testCheckRejectsAStaticallyDifferentNominalGrantor(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.feed', 'CREATE ROLE owner', 'CREATE ROLE other']);
        $grant = $semantics->analyze('GRANT SELECT ON SEQUENCE public.feed TO CURRENT_USER GRANTED BY other', $context);
        self::assertInstanceOf(GrantSequenceAccess::class, $grant);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A named schema grantor must match its named schema owner.');

        new CreateSchema(new Identifier('archive'), new Identifier('owner'), grants: [$grant]);
    }
}
