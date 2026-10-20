<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Large]
final class StructureSessionTransitionTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, M\Statement, int}>
     */
    public static function providerStructures(): iterable
    {
        $name = new Name("state ' あ");
        $xid = new M\Server\Transaction\Xa\Xid(new M\Literal\Text("state ' あ"), new M\Literal\Text('branch'), new M\Literal\Numeral('7'));
        $structures = [
            [new M\Dml\Handler\HandlerClose($name), 1109],
            [new M\Dml\Handler\HandlerScan(new M\Dml\Handler\OpenHandler($name), M\Dml\Handler\ScanDirection::First), 1109],
            [new M\Dml\Handler\HandlerIndexRead(new M\Dml\Handler\OpenHandler($name), new Name('PRIMARY'), M\Dml\Handler\IndexDirection::Last), 1109],
            [new M\Dml\Handler\HandlerIndexSeek(new M\Dml\Handler\OpenHandler($name), new Name('key_b'), M\Dml\Handler\KeyComparison::GreaterOrEqual, [new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('1')]), 1109],
            [new M\Server\ForeignServer\AlterServer($name, [new M\Server\ForeignServer\ServerOption(M\Server\ForeignServer\ServerOptionKind::User, new M\Literal\Text('reader'))]), 1477],
            [new M\Server\ForeignServer\DropServer(false, $name), 1477],
            [new M\Dml\Prepared\Execute($name, [new M\Variable\UserVariable(new Name('a')), new M\Variable\UserVariable(new Name('b'))]), 1243],
            [new M\Dml\Prepared\Deallocate($name), 1243],
            [new M\Server\Transaction\ReleaseSavepoint($name), 1305],
            [new M\Server\Transaction\RollbackToSavepoint($name), 1305],
            [new M\Server\Transaction\Xa\XaCommit($xid), 1397],
            [new M\Server\Transaction\Xa\XaCommit($xid, true), 1397],
            [new M\Server\Transaction\Xa\XaEnd($xid), 1399],
            [new M\Server\Transaction\Xa\XaPrepare($xid), 1399],
            [new M\Server\Transaction\Xa\XaRollback($xid), 1397],
        ];
        $role = new M\Name\AccountName(new Name("role ' あ"));
        $user = new M\Name\AccountName(new Name('role_recipient'));
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([
                [new M\Account\DropRole(false, [$role]), 1396],
                [new M\Account\Privilege\GrantRoles([new M\Account\Privilege\Item\GrantedRole($role)], [$user, new M\Name\CurrentUser(), new M\Name\CurrentUser()]), 3523],
                [new M\Account\AlterDefaultRole(false, $user, new M\Account\User\RoleSelection(M\Account\User\RoleSet::Named, [$role])), 3530],
            ] as $index => [$node, $code]) {
                yield $release->value . ':role:' . $index => [$release, $node, $code];
            }
        }
        $resource = new M\Account\ResourceGroup\ResourceGroupName(new Name(''));
        foreach ([GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([new M\Account\ResourceGroup\AlterResourceGroup($resource), new M\Account\ResourceGroup\DropResourceGroup($resource), new M\Account\ResourceGroup\SetResourceGroup($resource)] as $index => $node) {
                yield $release->value . ':resource:' . $index => [$release, $node, 3651];
            }
        }
        foreach ([GrammarRelease::MySql5744, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ($structures as $index => [$node, $code]) {
                yield $release->value . ':' . $index => [$release, $node, $code];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testTheOriginalStructureSucceedsOnceItsSessionStateExists(GrammarRelease $release, M\Statement $node, int $code): void
    {
        $before = get_object_vars($node);
        $observation = (new Comparison())->compare($node, new LanguageProfile($release));
        self::assertTrue($observation['matches'], serialize($observation));
        self::assertIsArray($observation['native']['error']);
        self::assertSame($code, $observation['native']['error'][0]);
        self::assertNotEmpty($observation['transitions']);
        $last = $observation['transitions'][count($observation['transitions']) - 1];
        self::assertSame($observation['sql'], $last['sql']);
        self::assertArrayNotHasKey('error', $last['native']);
        self::assertArrayNotHasKey('error', $last['memory']);
        self::assertSame($before, get_object_vars($node));
    }
}
