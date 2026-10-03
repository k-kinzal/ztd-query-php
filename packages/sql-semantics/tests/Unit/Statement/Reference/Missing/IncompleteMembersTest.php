<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;

#[CoversClass(IncompleteMembers::class)]
#[Small]
final class IncompleteMembersTest extends TestCase
{
    public function testDescribeNamesTheRelationWhoseMembersAreIncomplete(): void
    {
        $table = new Table(new QualifiedName(new Name('v')), new LanguageProfile(GrammarRelease::Sqlite3472), [], [], false);

        $missing = new IncompleteMembers($table);

        self::assertSame('the complete column list of relation v', $missing->describe());
        self::assertSame($table, $missing->table);
    }
}
