<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\MySql\StatementGenerator;

#[CoversNothing]
final class StatementGeneratorTest extends TestCase
{
    public function testStatementGeneratorUsesGenerationRequestSeedDeterministically(): void
    {
        $generator = new StatementGenerator(Factory::create(), 'mysql-8.0.44');

        self::assertSame(
            $generator->generate(new GenerationRequest('ident', 11, 1)),
            $generator->generate(new GenerationRequest('ident', 11, 1)),
        );
    }
}
