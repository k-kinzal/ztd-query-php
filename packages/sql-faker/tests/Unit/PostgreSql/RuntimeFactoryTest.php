<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Generation\GenerationRuntime;
use SqlFaker\PostgreSql\RuntimeFactory;

#[CoversNothing]
final class RuntimeFactoryTest extends TestCase
{
    public function testBuildReturnsRuntimeThatCanGenerateFromTheSupportedGrammar(): void
    {
        $runtime = RuntimeFactory::build(Factory::create());

        self::assertInstanceOf(GenerationRuntime::class, $runtime);
        self::assertSame('pg-17.2', $runtime->version());
        self::assertNotNull($runtime->supportedGrammar()->rule('stmtmulti'));
        self::assertNotSame([], $runtime->rewriteProgram()->stepIds());
        self::assertNotSame('', $runtime->generate(new GenerationRequest(startRule: 'ColId', seed: 17, maxDepth: 1)));
    }
}
