<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Type;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Type\InvalidReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InvalidReferenceTest extends TestCase
{
    public function testMissingReferenceCarriesADiagnosisNotAnUnknownFact(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT absent FROM bar');
        self::assertInstanceOf(\SqlSemantics\Semantic\Type\InvalidReference::class, $statement->field('absent')->type);
        self::assertSame('missing-column', $statement->field('absent')->type->diagnostic);
    }
}
