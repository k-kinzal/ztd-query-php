<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\GrantedRelations::class)]
#[Medium]
final class GrantedRelationsTest extends TestCase
{
    public function testDeriveReportsNothingForADeclaredTable(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('GRANT SELECT (id, price, ctid) ON items TO joe', [(new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE TABLE items (id int4, price int4)')])->facts->diagnostics));
    }

    public function testDeriveKeepsAnUndeclaredNameOpen(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('REVOKE SELECT (x) ON other FROM joe', [(new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE TABLE items (id int4, price int4)')])->facts->diagnostics));
    }

    public function testColumnsReportsEveryMissingColumn(): void
    {
        self::assertSame(['Column items.x does not exist.', 'Column items.y does not exist.'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('REVOKE SELECT (x), update (id, y) ON items FROM joe', [(new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE TABLE items (id int4, price int4)')])->facts->diagnostics));
    }
}
