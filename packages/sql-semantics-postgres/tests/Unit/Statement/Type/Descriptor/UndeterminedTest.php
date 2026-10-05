<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Undetermined;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;

#[CoversClass(Undetermined::class)]
#[Small]
final class UndeterminedTest extends TestCase
{
    public function testNameDescribesTheMissingInputs(): void
    {
        $type = new Undetermined([new UndeclaredDomain(new QualifiedName(new Name('mood')))]);
        self::assertSame('the type settled by the definition of data type mood', $type->name());
    }

    public function testNameJoinsSeveralMissingInputs(): void
    {
        $type = new Undetermined([new UndeclaredDomain(new QualifiedName(new Name('mood'))), new UnboundParameter('$1')]);
        self::assertSame('the type settled by the definition of data type mood and the value bound to parameter $1', $type->name());
    }
}
