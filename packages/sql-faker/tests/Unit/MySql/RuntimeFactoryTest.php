<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Generation\GenerationRuntime;
use SqlFaker\MySql\RuntimeFactory;

#[CoversNothing]
final class RuntimeFactoryTest extends TestCase
{
    public function testBuildReturnsRuntimeThatCanGenerateFromTheSupportedGrammar(): void
    {
        $runtime = RuntimeFactory::build(Factory::create());

        self::assertInstanceOf(GenerationRuntime::class, $runtime);
        self::assertSame('mysql-8.4.7', $runtime->version());
        self::assertNotNull($runtime->supportedGrammar()->rule('simple_statement_or_begin'));
        self::assertNotSame([], $runtime->rewriteProgram()->stepIds());
        self::assertNotSame('', $runtime->generate(new GenerationRequest(startRule: 'ident', seed: 17, maxDepth: 1)));
    }
}
