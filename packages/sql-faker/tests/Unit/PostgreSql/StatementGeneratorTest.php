<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\PostgreSql\StatementGenerator;

#[CoversNothing]
final class StatementGeneratorTest extends TestCase
{
    public function testStatementGeneratorUsesGenerationRequestSeedDeterministically(): void
    {
        $generator = new StatementGenerator(Factory::create());

        self::assertSame(
            $generator->generate(new GenerationRequest('ColId', 13, 1)),
            $generator->generate(new GenerationRequest('ColId', 13, 1)),
        );
    }
}
