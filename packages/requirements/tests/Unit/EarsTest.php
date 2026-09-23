<?php

declare(strict_types=1);

namespace Requirements\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\Validator;

final class EarsTest extends TestCase
{
    #[DataProvider('clauseOrderPatterns')]
    public function testClauseOrder(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function clauseOrderPatterns(): array
    {
        return [
            'complex' => ['While recording is enabled, When a token is read, the reader shall preserve its position.', true],
            'wrong order' => ['When input ends, while tracing is enabled, the reader shall return the tree.', false],
            'optional after state' => ['While tracing is enabled, where tracing exists, the reader shall return the tree.', false],
            'unknown keyword' => ['Unless input ends, the reader shall return the tree.', false],
        ];
    }

    #[DataProvider('systemResponsePatterns')]
    public function testSystemResponse(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function systemResponsePatterns(): array
    {
        return [
            'ubiquitous' => ['The reader shall preserve positions.', true],
            'no terminal punctuation' => ['The reader shall preserve positions', true],
            'no system' => ['The shall record positions.', false],
            'no article' => ['When input ends, reader shall return the tree.', false],
            'no response' => ['The reader shall .', false],
            'missing shall' => ['The reader will return the tree.', false],
        ];
    }

    #[DataProvider('stateDrivenPatterns')]
    public function testStateDriven(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function stateDrivenPatterns(): array
    {
        return [
            'state' => ['While recording is enabled, the reader shall preserve positions.', true],
            'no condition text' => ['While ..., the reader shall return the tree.', false],
        ];
    }

    #[DataProvider('eventDrivenPatterns')]
    public function testEventDriven(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function eventDrivenPatterns(): array
    {
        return [
            'event' => ['When a token is read, the reader shall preserve its position.', true],
            'no condition' => ['When , the reader shall return the tree.', false],
            'missing comma' => ['When input ends the reader shall return the tree.', false],
            'case insensitive' => ['WHEN input ends, THE reader SHALL emit the tree.', true],
            'comma in slot' => ['When a name, number or literal is read, the reader shall record its position.', true],
        ];
    }

    #[DataProvider('optionalFeaturePatterns')]
    public function testOptionalFeature(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function optionalFeaturePatterns(): array
    {
        return [
            'optional' => ['Where tracing is enabled, the reader shall preserve positions.', true],
            'optional complex' => ['Where tracing is enabled, while recording is enabled, when a token is read, the reader shall preserve its position.', true],
            'empty feature' => ['Where , the reader shall preserve positions.', false],
        ];
    }

    #[DataProvider('unwantedBehaviourPatterns')]
    public function testUnwantedBehaviour(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function unwantedBehaviourPatterns(): array
    {
        return [
            'unwanted' => ['If input is invalid, then the reader shall report an error.', true],
            'missing then' => ['If input ends, the reader shall return the tree.', false],
            'unexpected then' => ['When input ends, then the reader shall return the tree.', false],
            'then without if' => ['Then the reader shall return the tree.', false],
            'empty unwanted trigger' => ['If , then the reader shall report an error.', false],
        ];
    }

    #[DataProvider('complexPatterns')]
    public function testComplex(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function complexPatterns(): array
    {
        return [
            'complex' => ['While recording is enabled, When a token is read, the reader shall preserve its position.', true],
            'optional complex' => ['Where tracing is enabled, while recording is enabled, when a token is read, the reader shall preserve its position.', true],
            'wrong order' => ['When input ends, while tracing is enabled, the reader shall return the tree.', false],
        ];
    }

    #[DataProvider('cardinalityPatterns')]
    public function testCardinality(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function cardinalityPatterns(): array
    {
        return [
            'ubiquitous' => ['The reader shall preserve positions.', true],
            'multiple preconditions and responses' => ['While recording is enabled, while input is available, the reader shall preserve positions and report progress.', true],
            'two triggers' => ['When input ends, when a token is read, the reader shall return the tree.', false],
            'mixed triggers' => ['When input ends, if input is invalid, then the reader shall return the tree.', false],
            'two systems' => ['The reader shall return the tree, the printer shall print it.', false],
            'second shall' => ['The reader shall return the tree and shall print it.', false],
        ];
    }

    #[DataProvider('complexUnwantedBehaviourPatterns')]
    public function testComplexUnwantedBehaviour(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function complexUnwantedBehaviourPatterns(): array
    {
        return [
            'complex unwanted' => ['While recording is enabled, If input is invalid, then the reader shall report an error.', true],
            'complex missing then' => ['While recording is enabled, if input is invalid, the reader shall report an error.', false],
            'complex missing if' => ['While recording is enabled, then the reader shall report an error.', false],
            'complex empty trigger' => ['While recording is enabled, if , then the reader shall report an error.', false],
        ];
    }

    #[DataProvider('literalsPatterns')]
    public function testLiterals(string $statement, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('EARS:');
        }
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, bool}> */
    public static function literalsPatterns(): array
    {
        return [
            'quoted keywords' => ['When "if, then the" is read, the reader shall emit a "shall" token.', true],
            'apostrophe' => ["The reader shall preserve the user's input.", true],
            'code literal' => ['The reader shall preserve `shall, the` literally.', true],
            'open literal' => ['The reader shall emit a "token.', false],
        ];
    }

}
