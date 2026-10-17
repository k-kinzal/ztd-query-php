<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(LegacyForms::class)]
#[Small]
final class LegacyFormsTest extends TestCase
{
    public function testDefinitionDistinguishesLegacyInsertWildcardsFromColumnNames(): void
    {
        $forms = new LegacyForms();
        $name = new \SqlSemantics\Statement\Identifier\Name('t');

        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList([new \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard(new \SqlSemantics\Statement\Identifier\QualifiedName($name))])));
        self::assertFalse($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList([new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($name)])));
    }

    public function testGrantDistinguishesProxyAuthenticationFromPlainAccounts(): void
    {
        $forms = new LegacyForms();
        $account = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u'));
        $plain = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy($account, [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName($account->user))]);
        $identified = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy($account, [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName($account->user), new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('pw')))]);

        self::assertFalse($forms->grant($plain));
        self::assertTrue($forms->grant($identified));
    }

    public function testRequiresDistinguishesRemovedOptionsFromRetainedOptions(): void
    {
        $forms = new LegacyForms();

        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName(new \SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName(new \SqlSemantics\Statement\Identifier\Name('d')))));
        self::assertTrue($forms->requires(SelectOption::Cache));
        self::assertTrue($forms->requires(\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind::Redo));
        self::assertTrue($forms->requires(ExplainModifier::Partitions));
        self::assertFalse($forms->requires(SelectOption::NoCache));
    }

    public function testRequiresDistinguishesLegacyReplicationOperandsFromCurrentSynonyms(): void
    {
        $forms = new LegacyForms();
        $legacy = new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::LogPosition, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4'));
        $synonym = new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::LogPosition, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('4'), true);

        self::assertTrue($forms->requires($legacy));
        self::assertFalse($forms->requires($synonym));
    }
    public function testQueryDistinguishesLegacyOrderingFromPlainBlocks(): void
    {
        $body = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
        $ordered = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, $body, [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')))]);

        self::assertFalse((new LegacyForms())->query($body));
        self::assertTrue((new LegacyForms())->query($ordered));
    }

    public function testDefinitionRecognizesOnlyDefaultRoutineCollation(): void
    {
        $forms = new LegacyForms();
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, charset: new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Ascii));
        $name = new \SqlSemantics\Statement\Identifier\Name('arg');
        $default = new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter($name, $type, new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null));
        $named = new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter($name, $type, new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('latin1_bin')));

        self::assertTrue($forms->definition($default));
        self::assertFalse($forms->definition($named));
        self::assertFalse($forms->definition($type));
    }

    public function testDefinitionRecognizesDirectionsOnlyInGrouping(): void
    {
        $forms = new LegacyForms();
        $item = new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending);
        self::assertFalse($forms->requires($item));
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping([$item])));
        self::assertFalse($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping([new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))])));
        self::assertTrue($forms->requires(new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\UpgradePartitioning()));
    }

    public function testDefinitionRecognizesParameterNamesAcceptedOnlyBeforeEight(): void
    {
        $forms = new LegacyForms();
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int);

        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name(''), $type)));
        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name('x '), $type)));
        self::assertTrue($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name(str_repeat('猫', 65)), $type)));
        self::assertFalse($forms->definition(new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name(" x\t"), $type)));
    }

    public function testDefinitionRecognizesNamedDefaults(): void
    {
        self::assertTrue((new LegacyForms())->definition(new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption(new \SqlSemantics\Statement\Identifier\Name('DEFAULT'))));
        self::assertTrue((new LegacyForms())->definition(new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption(new \SqlSemantics\Statement\Identifier\Name('DEFAULT'))));
    }

    public function testDefinitionRecognizesLegacyViewColumnNames(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v'));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        self::assertTrue((new LegacyForms())->definition(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition($name, $query, [new \SqlSemantics\Statement\Identifier\Name('')])));
        self::assertFalse((new LegacyForms())->definition(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition($name, $query, [new \SqlSemantics\Statement\Identifier\Name('a')])));
    }

}
