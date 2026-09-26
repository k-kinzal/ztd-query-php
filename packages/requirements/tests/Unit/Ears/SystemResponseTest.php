<?php

declare(strict_types=1);

namespace Tests\Unit\Ears;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Wording;
use Requirements\Input\InvalidInputException;

#[CoversClass(SystemResponse::class)]
#[UsesClass(Wording::class)]
#[Small]
final class SystemResponseTest extends TestCase
{
    #[DataProvider('providerValidateAcceptsSystemClauses')]
    public function testValidateAcceptsSystemClauses(string $clause, ?string $trigger): void
    {
        (new SystemResponse())->validate($clause, $trigger);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function providerValidateAcceptsSystemClauses(): array
    {
        return [
            'ubiquitous' => ['The reader shall preserve positions.', null],
            'after when' => ['the reader shall preserve positions.', 'when'],
            'after if' => ['then the reader shall report an error.', 'if'],
            'uppercase' => ['THEN THE READER SHALL REPORT.', 'if'],
            'surrounding space' => ["  The reader shall read \n", null],
            'multiword system' => ['The token reader of the parser shall read.', null],
            'multiline response' => ["The reader shall read\nand write.", null],
            'word containing shall' => ['The reader shall read shallow files.', null],
        ];
    }

    #[DataProvider('providerValidateRejectsClausesWithoutThe')]
    public function testValidateRejectsClausesWithoutThe(string $clause, ?string $trigger): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: expected The <system name> shall <system response>.');
        (new SystemResponse())->validate($clause, $trigger);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function providerValidateRejectsClausesWithoutThe(): array
    {
        return [
            'no article' => ['reader shall return the tree.', 'when'],
            'then without if' => ['then the reader shall return the tree.', 'when'],
            'then without trigger' => ['Then the reader shall return the tree.', null],
            'no system' => ['The shall record positions.', null],
            'punctuation system' => ['The ... shall record positions.', null],
            'no response' => ['The reader shall .', null],
            'empty response' => ['The reader shall', null],
            'missing shall' => ['The reader will return the tree.', null],
            'article joined to word' => ['Thereader shall read.', null],
        ];
    }

    #[DataProvider('providerValidateRejectsClausesWithoutThenAfterIf')]
    public function testValidateRejectsClausesWithoutThenAfterIf(string $clause): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: expected Then the <system name> shall <system response>.');
        (new SystemResponse())->validate($clause, 'if');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerValidateRejectsClausesWithoutThenAfterIf(): array
    {
        return [
            'missing then' => ['the reader shall return the tree.'],
            'then without article' => ['then reader shall return the tree.'],
            'no system' => ['then the shall report.'],
            'no response' => ['then the reader shall !'],
        ];
    }

    #[DataProvider('providerValidateRejectsASecondShall')]
    public function testValidateRejectsASecondShall(string $clause): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS: use one system clause; combine responses after its shall.');
        (new SystemResponse())->validate($clause, null);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerValidateRejectsASecondShall(): array
    {
        return [
            'second shall' => ['The reader shall return the tree and shall print it.'],
            'two systems' => ['The reader shall return the tree, the printer shall print it.'],
            'uppercase second shall' => ['The reader shall read and SHALL print.'],
        ];
    }
}
