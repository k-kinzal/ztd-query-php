<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Account\Privilege\LegacyColumns;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(LegacyColumns::class)]
#[Medium]
final class LegacyColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerTargets(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744] as $release) {
            foreach (['GRANT', 'REVOKE'] as $verb) {
                yield $release->value . ':' . $verb => [$release, $verb];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerTargets')]
    public function testCompletedRefusesTheTargetBeforeAnUnfinishedRecipientList(GrammarRelease $release, string $verb): void
    {
        $parser = new MySqlParser($release->value);
        $profile = new LanguageProfile($release);
        $prefix = $verb . ' SELECT(a) ON d.*';
        $notices = (new SyntaxWarnings())->completed($parser, $prefix . ' TO ?', 100, $profile);
        self::assertCount(1, $notices);
        self::assertSame(strlen($prefix), $notices[0][0]);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertTrue($notices[0][1]->aborts);
        self::assertInstanceOf(RefusedColumnLevel::class, $notices[0][1]->problem);
        self::assertSame('a', $notices[0][1]->problem->privilege->columns[0]->value);
    }

    public function testScopedCompleteAndIncompleteProgramInputsRetainTheSameScope(): void
    {
        $parser = new MySqlParser('mysql-5.6.51');
        $profile = new LanguageProfile(GrammarRelease::MySql5651);
        $prefix = 'CREATE PROCEDURE d.p() GRANT SELECT(a) ON *';
        $failure = (new SyntaxWarnings())->rejected($parser->parse($prefix . ' TO _sqlfaker_identifier'), $profile);
        self::assertNotNull($failure);
        self::assertInstanceOf(RefusedColumnLevel::class, $failure->problem);
        self::assertSame(strlen($prefix), $failure->end);
        self::assertSame('d', $failure->problem->program?->schema?->value);
        $notices = (new SyntaxWarnings())->completed($parser, $prefix . ' TO ?', 100, $profile);
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertEquals($failure->problem, $notices[0][1]->problem);
    }

    public function testRejectedRecognizesACompletedRevokeTarget(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51');
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse('REVOKE SELECT(a) ON d.* FROM u'), $semantics->profile());
        self::assertNotNull($failure);
        self::assertInstanceOf(RefusedColumnLevel::class, $failure->problem);
        self::assertSame(strlen('REVOKE SELECT(a) ON d.*'), $failure->end);
    }

    public function testRequireTableRejectsACompletedColumnPrivilegeWithoutRecipients(): void
    {
        $privilege = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [new \SqlSemantics\Statement\Identifier\Name('a')]);
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('A column privilege requires an individual table target.');
        (new LegacyColumns())->requireTable([$privilege], new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), 22);
    }
}
