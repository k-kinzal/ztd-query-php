<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\ScalarInput::class)]
#[Small]
final class ScalarInputTest extends TestCase
{
    public function testExternalInterfaceImplementationsCannotEnterAQuerySnapshot(): void
    {
        $external = new class () implements C\ScalarInput {};
        $context = new Catalog(new SearchPath(new Name('main')));
        $this->expectException(\SqlSemantics\Statement\Validation\Failure\InvalidConstruction::class);
        new Select($context, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($external))));
    }
}
