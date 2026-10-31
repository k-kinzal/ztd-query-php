<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json\DeferredJsonValueCall;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DeferredJsonValueCall::class)]
#[Medium]
final class DeferredJsonValueCallTest extends TestCase
{
    public function testDeriveScalarRetainsDocumentAndFallbackDependenciesWithoutPublishingADiagnostic(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $path = new M\Literal\StringLiteral(['$']);
        $fallback = new M\Literal\StringLiteral(['fallback']);
        $call = new DeferredJsonValueCall($row, $path, new M\Type\CastTarget(M\Type\Kind\CastKind::Char, '10'), new M\Call\Json\JsonResponse(M\Call\Json\JsonResponseKind::Default, $fallback));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($call, $derivation->environment());

        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertTrue($row->hasBinding());
        self::assertTrue($path->hasBinding());
        self::assertTrue($fallback->hasBinding());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsPreservesDocumentAndPathIdentity(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $path = new M\Literal\StringLiteral(['$']);
        $empty = new M\Literal\StringLiteral(['empty']);
        $error = new M\Literal\StringLiteral(['error']);
        $call = new DeferredJsonValueCall($row, $path, onEmpty: new M\Call\Json\JsonResponse(M\Call\Json\JsonResponseKind::Default, $empty), onError: new M\Call\Json\JsonResponse(M\Call\Json\JsonResponseKind::Default, $error));

        self::assertSame([$row, $path], $call->operands());
        self::assertSame($empty, $call->onEmpty?->default);
        self::assertSame($error, $call->onError?->default);
    }

    public function testProblemRetainsTheRequiredScalarWidth(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new DeferredJsonValueCall($row, new M\Literal\StringLiteral(['$'])))->problem()->message());
    }

    public function testRenderPreservesTypedOptionsInAStoredInstruction(): void
    {
        $sql = "CREATE PROCEDURE p() DO JSON_VALUE(ROW(1, 2), '$' RETURNING CHAR(10) DEFAULT 'empty' ON EMPTY ERROR ON ERROR)";
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertSame($sql, $operation->toString());
    }

}
