<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Digest;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Digest\Addresses;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Addresses::class)]
#[Small]
final class AddressesTest extends TestCase
{
    public function testRoutinesNamesTheAddressFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Addresses())->routines());

        self::assertSame(['INET_ATON', 'INET_NTOA', 'INET6_ATON', 'INET6_NTOA', 'IS_IPV4', 'IS_IPV6', 'IS_IPV4_COMPAT', 'IS_IPV4_MAPPED'], $names);
    }

    public function testTestAnswersZeroForNullIn57(): void
    {
        $modern = (new Instance())->connect()->query("SELECT IS_IPV4(NULL), IS_IPV6('::1'), IS_IPV4('10.0.5.256')")[0];
        $legacy = (new Instance('5.7.44'))->connect()->query('SELECT IS_IPV4(NULL), IS_IPV6(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $modern);
        self::assertInstanceOf(ResultSet::class, $legacy);
        self::assertSame([[[null, '1', '0']], [['0', '0']]], [$modern->rows, $legacy->rows]);
    }

    public function testBinaryTellsWhetherAnArgumentIsABinaryString(): void
    {
        self::assertSame([true, false], [(new Addresses())->binary(new Constant(Domain::string(4, Collation::binary()), 'abcd')), (new Addresses())->binary(new Constant(Domain::string(4, Collation::known('latin1_swedish_ci')), 'abcd'))]);
    }

    public function testArgumentTakesTheArgumentOfTheCallText(): void
    {
        self::assertSame("'1.2'", (new Addresses())->argument("inet_aton('1.2')"));
    }

    public function testInetAtonWarnsOfATextThatIsNotAnAddress(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INET_ATON('127.1'), INET_ATON('1..2'), INET_ATON('256.0.0.1'), INET_ATON(16909060)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['2130706433', '16777218', null, null]], $result->rows);
        self::assertSame([['Warning', '1411', "Incorrect string value: ''256.0.0.1'' for function inet_aton"], ['Warning', '1411', "Incorrect string value: '16909060' for function inet_aton"]], $warnings->rows);
    }

    public function testNumberReadsTheShortForms(): void
    {
        $addresses = new Addresses();

        self::assertSame([1, 16909060, null, null, null], [$addresses->number('.1'), $addresses->number('01.02.03.0004'), $addresses->number('1.'), $addresses->number('1.2.3.4.5'), $addresses->number('')]);
    }

    public function testInetNtoaWarnsOfANumberOutOfRange(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT INET_NTOA(16909060), INET_NTOA(1.6), INET_NTOA(-1)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['1.2.3.4', '0.0.0.2', null]], $result->rows);
        self::assertSame([['Warning', '1411', "Incorrect integer value: '-(1)' for function inet_ntoa"]], $warnings->rows);
    }

    public function testInetNtoaRaisesTheWarningAsAnErrorInAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE w (s VARCHAR(20))');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect integer value: '-(1)' for function inet_ntoa");

        $session->query('INSERT INTO w SELECT INET_NTOA(-1)');
    }

    public function testIpv4ReadsFourNumbersOfAtMostThreeDigits(): void
    {
        self::assertSame(["\x01\x02\x03\x04", null, null], [(new Addresses())->ipv4('01.2.3.004'), (new Addresses())->ipv4('1.2.3.0004'), (new Addresses())->ipv4('127.1')]);
    }

    public function testIpv6ReadsTheCompressedAndEmbeddedForms(): void
    {
        $addresses = new Addresses();

        self::assertSame(['00010000000200030004000500060007', '00010002000300040005000001020304', null, null, null], [bin2hex((string) $addresses->ipv6('1::2:3:4:5:6:7')), bin2hex((string) $addresses->ipv6('1:2:3:4:5::1.2.3.4')), $addresses->ipv6('1:2:3:4:5:6::1.2.3.4'), $addresses->ipv6(':1::'), $addresses->ipv6('00001::')]);
    }

    public function testGroupsReadsAnIpv4AddressOnlyLast(): void
    {
        self::assertSame(['0001' . '01020304', null], [bin2hex((string) (new Addresses())->groups('1:1.2.3.4', true)), (new Addresses())->groups('1.2.3.4:1', true)]);
    }

    public function testInet6AtonAnswersFourOrSixteenBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(INET6_ATON('127.0.0.1')), HEX(INET6_ATON('fdfe::5a55:caff:fefa:9089')), INET6_ATON(' ::1')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['7F000001', 'FDFE0000000000005A55CAFFFEFA9089', null]], $result->rows);
        self::assertSame([['Warning', '1411', "Incorrect string value: '' ::1'' for function inet6_aton"]], $warnings->rows);
    }

    public function testInet6NtoaRefusesAStringThatIsNotBinary(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INET6_NTOA(X'01020304'), INET6_NTOA('abcd'), INET6_NTOA(X'0102')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['1.2.3.4', null, null]], $result->rows);
        self::assertSame([['Warning', '1411', "Incorrect string value: ''abcd'' for function inet6_ntoa"], ['Warning', '1411', "Incorrect string value: '0x0102' for function inet6_ntoa"]], $warnings->rows);
    }

    public function testAddressCompressesTheFirstLongestRunOfZeros(): void
    {
        $addresses = new Addresses();

        self::assertSame(['1::2:0:3:0:4:0', '1::2:0:0:3:4', '::ffff:1.2.3.4', '::0.1.0.255', '::101', '::'], [$addresses->address((string) hex2bin('00010000000200000003000000040000')), $addresses->address((string) hex2bin('00010000000000020000000000030004')), $addresses->address((string) hex2bin('00000000000000000000ffff01020304')), $addresses->address((string) hex2bin('000000000000000000000000000100ff')), $addresses->address((string) hex2bin('00000000000000000000000000000101')), $addresses->address(str_repeat("\0", 16))]);
    }

    public function testRoutinesTellIpv4CompatibleAndMappedAddresses(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT IS_IPV4_COMPAT(INET6_ATON('::10.0.5.9')), IS_IPV4_COMPAT(INET6_ATON('::1')), IS_IPV4_COMPAT(INET6_ATON('::2')), IS_IPV4_MAPPED(INET6_ATON('::ffff:10.0.5.9')), IS_IPV4_COMPAT('0000000000000000')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '1', '0']], $result->rows);
    }
}
