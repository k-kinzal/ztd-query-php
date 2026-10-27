<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\DeclarationErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclarationErrors::class)]
#[Small]
final class DeclarationErrorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerContradictions(): iterable
    {
        yield 'columns' => ['a INT,a INT', 1060];
        yield 'primary' => ['a INT,PRIMARY KEY(a),PRIMARY KEY(a)', 1068];
        yield 'reference' => ['a INT,KEY(b)', 1072];
        yield 'parts' => ['a INT,KEY(a,a)', 1060];
    }

    #[DataProvider('providerContradictions')]
    public function testCheckPreservesSemanticConditionsBeforeStorage(string $elements, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $reply = $session->run('CREATE TABLE t(' . $elements . ')')[0];
        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    #[DataProvider('providerContradictions')]
    public function testLayoutReportsStateDependentContradictionsBeforeConstructingAStatement(string $elements, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $programAnalysis = $session->analyze('CREATE PROCEDURE p() CREATE TABLE t(' . $elements . ')');
        $program = $programAnalysis;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $program);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $program->body);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);

        (new DeclarationErrors())->layout($program->body->elements);
    }

    public function testLayoutAllowsACompleteConsistentDeclaration(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $createAnalysis = $session->analyze('CREATE TABLE t(a INT,KEY(a))');
        $create = $createAnalysis;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $create);

        (new DeclarationErrors())->layout($create->elements);
        self::assertCount(2, $create->elements);
    }

}
