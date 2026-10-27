<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\UnrepresentableName;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(UnrepresentableName::class)]
#[Medium]
final class UnrepresentableNameTest extends TestCase
{
    public function testToStringRefusesLossyEncodingWithoutRejectingTheStructure(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $statement = $semantics->analyze('SELECT 1 AS `猫`');
        $context = $semantics->context(null, session: new Settings(Collation::known('latin1_swedish_ci'), client: Charset::known('latin1')));
        $operation = Semantics::fromStatement($context, $statement);
        self::assertNotSame($statement, $operation);
        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference($statement, $operation));
        self::assertSame('猫', $operation->field(0)->name?->value);
        $this->expectException(UnrepresentableName::class);
        $operation->toString();
    }
}
