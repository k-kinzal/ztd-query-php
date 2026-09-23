<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropUserMappingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MappingPrincipal::class)]
#[Medium]
final class MappingPrincipalTest extends TestCase
{
    #[TestWith(['CURRENT_USER', MappingPrincipal::CurrentUser])]
    #[TestWith(['CURRENT_ROLE', MappingPrincipal::CurrentRole])]
    #[TestWith(['SESSION_USER', MappingPrincipal::SessionUser])]
    #[TestWith(['PUBLIC', MappingPrincipal::PublicDefault])]
    public function testEachPrincipalIsRetainedSymbolically(string $sqlPrincipal, MappingPrincipal $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP USER MAPPING FOR ' . $sqlPrincipal . ' SERVER remote');
        self::assertInstanceOf(DropUserMappingStatement::class, $statement);
        self::assertSame($expected, $statement->target->user);
    }

}
