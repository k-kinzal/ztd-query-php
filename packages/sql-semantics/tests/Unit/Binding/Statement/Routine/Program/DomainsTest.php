<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Domains;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Domains::class)]
#[Medium]
final class DomainsTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', 'VARCHAR(5) CHARACTER SET latin1 COLLATE latin1_bin', 'latin1_bin'])]
    #[TestWith(['mysql-5.7.44', 'VARCHAR(5) CHARACTER SET latin1 COLLATE DEFAULT', null])]
    #[TestWith(['mysql-8.0.44', 'VARCHAR(5) COLLATE utf8mb4_bin', 'utf8mb4_bin'])]
    #[TestWith(['mysql-8.4.7', 'CHAR(1) COLLATE BINARY', 'binary'])]
    public function testReadReturnsTheCollation(string $version, string $type, ?string $collation): void
    {
        $tree = (new DialectParser(Dialect::MySql, $version))->parse('CREATE PROCEDURE p(a ' . $type . ') BEGIN END');
        $parameter = Tree::outer($tree, ['sp_pdparam'])[0];
        self::assertSame($collation, Domains::read($parameter, new Identifiers(Dialect::MySql))->collation);
    }

    public function testReadDiagnosesALegacyCollationWithoutACharacterSet(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE a CHAR(1) COLLATE latin1_bin; END', strict: false);
    }

    #[TestWith(['CHAR(1) CHARACTER SET latin1', true])]
    #[TestWith(['NATIONAL CHAR(1)', true])]
    #[TestWith(['CHARACTER VARYING(3)', false])]
    #[TestWith(['INT', false])]
    public function testCharsetDetectsADeclaredCharacterSet(string $type, bool $charset): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('CREATE PROCEDURE p(a ' . $type . ') BEGIN END');
        self::assertSame($charset, Domains::charset(Tree::outer($tree, ['type'])[0]));
    }
}
