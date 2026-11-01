<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\DeclarationScope;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(DeclarationScope::class)]
#[Medium]
final class DeclarationScopeTest extends TestCase
{
    public function testCheckAcceptsTheDeclarationsTheStatementProvided(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $context = $semantics->context([]);
        $derivation = new Derivation($context);
        $derivation->statement((new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('CREATE TABLE t (a INTEGER)')));
        $own = new AnalysisContext($context->profile, $context->searchPath, $derivation->facts()->declarations, true, $context->relationNames, $context->columnNames);

        (new DeclarationScope())->check($context, $own, $derivation->facts()->declarations);

        self::assertCount(1, $derivation->facts()->declarations);
    }

    public function testCheckRefusesAContextThatDropsAnEnclosingDeclaration(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE t (a INTEGER)')]);
        $empty = new AnalysisContext($context->profile, $context->searchPath, [], true);

        $this->expectExceptionMessage('A nested statement sees every declaration of the enclosing context.');

        (new DeclarationScope())->check($context, $empty, []);
    }

}
