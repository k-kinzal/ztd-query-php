<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Definition\Ownership\OwnershipInvariant::class)]
#[Medium]
final class OwnershipInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testOwnersRejectsAnotherDatabaseLanguage(Dialect $dialect): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        \SqlSemantics\Model\Definition\Ownership\OwnershipInvariant::owners($origin, [SessionRole::CurrentRole]);
    }

}
