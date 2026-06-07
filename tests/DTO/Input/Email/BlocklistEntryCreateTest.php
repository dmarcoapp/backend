<?php

declare(strict_types=1);

namespace App\Tests\DTO\Input\Email;

use App\DTO\Input\User\BlocklistEntryCreate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[CoversClass(BlocklistEntryCreate::class)]
final class BlocklistEntryCreateTest extends TestCase
{
    public function testConstructorSetsPattern(): void
    {
        $dto = new BlocklistEntryCreate('user@example.com');

        self::assertSame('user@example.com', $dto->pattern);
    }

    #[DataProvider('validPatternProvider')]
    public function testValidatePatternAcceptsValidEdgeCases(string $pattern): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        BlocklistEntryCreate::validatePattern($pattern, $context, null);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function validPatternProvider(): array
    {
        $maxLocal = str_repeat('a', 64);
        $maxDomainLabel = str_repeat('b', 63);
        $maxDomain = str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61);

        return [
            ['simple@example.com'],
            ['john.doe@example.com'],
            ['john_doe+tag-123@example-domain.com'],
            ['a@b.co'],
            ['user.name+tag@sub.example.co.uk'],
            ['*@example.com'],
            ['*@*.example.com'],
            ['*@mail.*'],
            ['*.*@*.example.com'],
            ['user*@example.com'],
            ['*user@example.com'],
            ['user.*@example.com'],
            ['user@exa*mple.com'],
            ['user@sub-*.*.example.com'],
            ['user+tag@example.com'],
            ['A_B-123@Sub-Domain.Example'],
            [$maxLocal.'@example.com'],
            ['user@'.$maxDomainLabel.'.com'],
            ['user@'.$maxDomain],
        ];
    }

    #[DataProvider('invalidPatternProvider')]
    public function testValidatePatternRejectsInvalidPatterns(string $pattern): void
    {
        $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->method('addViolation')->willReturnCallback(static function (): void {});

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::atLeastOnce())->method('buildViolation')->willReturn($builder);

        BlocklistEntryCreate::validatePattern($pattern, $context, null);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidPatternProvider(): array
    {
        $tooLongLocal = str_repeat('a', 65);
        $tooLongDomainLabel = str_repeat('b', 64);
        $tooLongDomain = str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 62);

        return [
            ['*@example.com,foobar.com'],
            ['asd@asd/example.com'],
            ['customer/department=shipping@example.com'],
            ["o'reilly@example.com"],
            ['"john"@example.com'],
            ['+user@example.com'],
            ['user+@example.com'],
            ['-user@example.com'],
            ['user-@example.com'],
            ['_user@example.com'],
            ['user_@example.com'],
            ['%user@example.com'],
            ['user%@example.com'],
            ['john..doe@example.com'],
            ['.john@example.com'],
            ['john.@example.com'],
            ['john@-example.com'],
            ['john@example-.com'],
            ['john@example..com'],
            ['john@example'],
            ['john@*'],
            ['*@*'],
            ['*@*.*'],
            ['*@*.*.*'],
            ['**@*.*'],
            ['*@**.*'],
            ['*@*.**'],
            ['*@**.*.*'],
            ['*@*.**.*'],
            ['john@localhost'],
            ['john@@example.com'],
            ['johnexample.com'],
            ['@example.com'],
            ['john@'],
            ['john doe@example.com'],
            ['john@exa mple.com'],
            ['john@exa_mple.com'],
            ['john@.example.com'],
            ['john@example.com.'],
            ['john@-*.example.com'],
            ['árvíz@example.com'],
            [$tooLongLocal.'@example.com'],
            ['john@'.$tooLongDomainLabel.'.com'],
            ['john@'.$tooLongDomain],
        ];
    }

    public function testValidatePatternThrowsWhenNotString(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        BlocklistEntryCreate::validatePattern(123, $this->createStub(ExecutionContextInterface::class), null);
    }

    public function testValidatePatternAcceptsNullValue(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        BlocklistEntryCreate::validatePattern(null, $context, null);
    }

    public function testValidatePatternRejectsWhitespaceOnlyPattern(): void
    {
        $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->method('addViolation')->willReturnCallback(static function (): void {});

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())->method('buildViolation')->with('Pattern must not be empty.')->willReturn($builder);

        BlocklistEntryCreate::validatePattern('   ', $context, null);
    }
}
