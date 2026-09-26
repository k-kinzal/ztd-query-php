<?php

declare(strict_types=1);

namespace Tests\Unit\Ears;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\LiteralMask;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Validator;
use Requirements\Ears\Wording;
use Requirements\Input\InvalidInputException;

#[CoversClass(Validator::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Wording::class)]
#[Small]
final class ValidatorTest extends TestCase
{
    #[DataProvider('providerClauseOrderAccepted')]
    public function testValidateAcceptsClauseOrder(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerClauseOrderAccepted(): array
    {
        return [
            'complex' => ['While recording is enabled, When a token is read, the reader shall preserve its position.'],
        ];
    }

    #[DataProvider('providerClauseOrderRejected')]
    public function testValidateRejectsClauseOrder(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerClauseOrderRejected(): array
    {
        return [
            'wrong order' => ['When input ends, while tracing is enabled, the reader shall return the tree.'],
            'optional after state' => ['While tracing is enabled, where tracing exists, the reader shall return the tree.'],
            'unknown keyword' => ['Unless input ends, the reader shall return the tree.'],
        ];
    }

    #[DataProvider('providerSystemResponseAccepted')]
    public function testValidateAcceptsSystemResponse(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSystemResponseAccepted(): array
    {
        return [
            'ubiquitous' => ['The reader shall preserve positions.'],
            'no terminal punctuation' => ['The reader shall preserve positions'],
        ];
    }

    #[DataProvider('providerSystemResponseRejected')]
    public function testValidateRejectsSystemResponse(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSystemResponseRejected(): array
    {
        return [
            'no system' => ['The shall record positions.'],
            'no article' => ['When input ends, reader shall return the tree.'],
            'no response' => ['The reader shall .'],
            'missing shall' => ['The reader will return the tree.'],
        ];
    }

    #[DataProvider('providerStateDrivenAccepted')]
    public function testValidateAcceptsStateDriven(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerStateDrivenAccepted(): array
    {
        return [
            'state' => ['While recording is enabled, the reader shall preserve positions.'],
        ];
    }

    #[DataProvider('providerStateDrivenRejected')]
    public function testValidateRejectsStateDriven(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerStateDrivenRejected(): array
    {
        return [
            'no condition text' => ['While ..., the reader shall return the tree.'],
        ];
    }

    #[DataProvider('providerEventDrivenAccepted')]
    public function testValidateAcceptsEventDriven(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerEventDrivenAccepted(): array
    {
        return [
            'event' => ['When a token is read, the reader shall preserve its position.'],
            'case insensitive' => ['WHEN input ends, THE reader SHALL emit the tree.'],
            'comma in slot' => ['When a name, number or literal is read, the reader shall record its position.'],
        ];
    }

    #[DataProvider('providerEventDrivenRejected')]
    public function testValidateRejectsEventDriven(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerEventDrivenRejected(): array
    {
        return [
            'no condition' => ['When , the reader shall return the tree.'],
            'missing comma' => ['When input ends the reader shall return the tree.'],
        ];
    }

    #[DataProvider('providerOptionalFeatureAccepted')]
    public function testValidateAcceptsOptionalFeature(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerOptionalFeatureAccepted(): array
    {
        return [
            'optional' => ['Where tracing is enabled, the reader shall preserve positions.'],
            'optional complex' => ['Where tracing is enabled, while recording is enabled, when a token is read, the reader shall preserve its position.'],
        ];
    }

    #[DataProvider('providerOptionalFeatureRejected')]
    public function testValidateRejectsOptionalFeature(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerOptionalFeatureRejected(): array
    {
        return [
            'empty feature' => ['Where , the reader shall preserve positions.'],
        ];
    }

    #[DataProvider('providerUnwantedBehaviourAccepted')]
    public function testValidateAcceptsUnwantedBehaviour(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnwantedBehaviourAccepted(): array
    {
        return [
            'unwanted' => ['If input is invalid, then the reader shall report an error.'],
        ];
    }

    #[DataProvider('providerUnwantedBehaviourRejected')]
    public function testValidateRejectsUnwantedBehaviour(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnwantedBehaviourRejected(): array
    {
        return [
            'missing then' => ['If input ends, the reader shall return the tree.'],
            'unexpected then' => ['When input ends, then the reader shall return the tree.'],
            'then without if' => ['Then the reader shall return the tree.'],
            'empty unwanted trigger' => ['If , then the reader shall report an error.'],
        ];
    }

    #[DataProvider('providerComplexAccepted')]
    public function testValidateAcceptsComplex(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerComplexAccepted(): array
    {
        return [
            'complex' => ['While recording is enabled, When a token is read, the reader shall preserve its position.'],
            'optional complex' => ['Where tracing is enabled, while recording is enabled, when a token is read, the reader shall preserve its position.'],
        ];
    }

    #[DataProvider('providerComplexRejected')]
    public function testValidateRejectsComplex(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerComplexRejected(): array
    {
        return [
            'wrong order' => ['When input ends, while tracing is enabled, the reader shall return the tree.'],
        ];
    }

    #[DataProvider('providerCardinalityAccepted')]
    public function testValidateAcceptsCardinality(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerCardinalityAccepted(): array
    {
        return [
            'ubiquitous' => ['The reader shall preserve positions.'],
            'multiple preconditions and responses' => ['While recording is enabled, while input is available, the reader shall preserve positions and report progress.'],
        ];
    }

    #[DataProvider('providerCardinalityRejected')]
    public function testValidateRejectsCardinality(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerCardinalityRejected(): array
    {
        return [
            'two triggers' => ['When input ends, when a token is read, the reader shall return the tree.'],
            'mixed triggers' => ['When input ends, if input is invalid, then the reader shall return the tree.'],
            'two systems' => ['The reader shall return the tree, the printer shall print it.'],
            'second shall' => ['The reader shall return the tree and shall print it.'],
        ];
    }

    #[DataProvider('providerComplexUnwantedBehaviourAccepted')]
    public function testValidateAcceptsComplexUnwantedBehaviour(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerComplexUnwantedBehaviourAccepted(): array
    {
        return [
            'complex unwanted' => ['While recording is enabled, If input is invalid, then the reader shall report an error.'],
        ];
    }

    #[DataProvider('providerComplexUnwantedBehaviourRejected')]
    public function testValidateRejectsComplexUnwantedBehaviour(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerComplexUnwantedBehaviourRejected(): array
    {
        return [
            'complex missing then' => ['While recording is enabled, if input is invalid, the reader shall report an error.'],
            'complex missing if' => ['While recording is enabled, then the reader shall report an error.'],
            'complex empty trigger' => ['While recording is enabled, if , then the reader shall report an error.'],
        ];
    }

    #[DataProvider('providerLiteralsAccepted')]
    public function testValidateAcceptsLiterals(string $statement): void
    {
        (new Validator())->validate($statement);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerLiteralsAccepted(): array
    {
        return [
            'quoted keywords' => ['When "if, then the" is read, the reader shall emit a "shall" token.'],
            'apostrophe' => ["The reader shall preserve the user's input."],
            'code literal' => ['The reader shall preserve `shall, the` literally.'],
        ];
    }

    #[DataProvider('providerLiteralsRejected')]
    public function testValidateRejectsLiterals(string $statement): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS:');
        (new Validator())->validate($statement);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerLiteralsRejected(): array
    {
        return [
            'open literal' => ['The reader shall emit a "token.'],
        ];
    }
}
