<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\IR;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ProgramTest extends TestCase
{
    public function testCallablePreservesTheSemanticContract(): void
    {
        self::assertContains(\Deriver\Internal\IR\Program::class, class_implements(\Deriver\Internal\Frontend\Php\ProjectIndex::class));
    }
    public function testClassesIncludesDeclarationsBeforeAnyGraphIsDemanded(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php class A {}');
        self::assertSame(['a'], array_keys($index->classes()));
        self::assertSame(0, $index->graphCount());
    }
    public function testSymbolsReturnsStableSourceIdentities(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function Z(){} function A(){}');
        self::assertSame(['A', 'Z', 'script:fixture.php'], $index->symbols());
    }
    public function testDiagnosticsKeepsSyntaxErrorsSeparateFromAnEmptyProgram(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function broken(');
        self::assertNotEmpty($index->diagnostics());
        self::assertSame('INCOMPLETE_SOURCE', $index->diagnostics()[0]->code);
        self::assertSame([], $index->symbols());
    }
    public function testGraphCountCountsOnlyDemandedBodiesAndReusesTheGraph(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function target(){} function other(){}');
        self::assertSame(0, $index->graphCount());
        $first = $index->callable('target');
        self::assertNotNull($first);
        self::assertSame($first, $index->callable('TARGET'));
        self::assertSame(1, $index->graphCount());
    }
    public function testConstantCompilesARequestedInitializerOnce(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php const ANSWER = 40+2;');
        $constant = $index->constant('ANSWER');
        self::assertNotNull($constant);
        self::assertSame(['constant', 'constant', 'binary'], array_column($constant->blocks[0]->instructions, 'operation'));
        self::assertSame($constant, $index->constant('ANSWER'));
        self::assertNull($index->constant('answer'));
    }
    public function testCallOwnersDoesNotCompileUnrelatedBodies(): void
    {
        $index = \Tests\Fake\FrontendFixture::index('<?php function used(){sink(1);} function other(){unrelated();}');
        self::assertSame(['used'], $index->callOwners('sink'));
        self::assertSame(0, $index->graphCount());
    }
}
