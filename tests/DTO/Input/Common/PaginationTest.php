<?php

declare(strict_types=1);

namespace App\Tests\DTO\Input\Common;

use App\DTO\Input\Common\Pagination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[CoversClass(Pagination::class)]
final class PaginationTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $pagination = new Pagination(
            page: 2,
            pageSize: 25,
            filter: ['eq:domain' => 'example.com'],
            sort: ['domain' => 'desc'],
        );

        self::assertSame(2, $pagination->page);
        self::assertSame(25, $pagination->pageSize);
        self::assertSame(['eq:domain' => 'example.com'], $pagination->filter);
        self::assertSame(['domain' => 'desc'], $pagination->sort);
    }

    public function testValidateFilterReportsInvalidEntries(): void
    {
        $context = $this->createContextMockExpectingViolations();

        Pagination::validateFilter(
            [
                'eq:empty-value' => '',
                'invalid' => 'value',
                'eq:' => 'missing-field',
                'badop:domain' => 'example.com',
                'eq:domain' => 'example.com',
            ],
            $context,
            null
        );

        self::assertTrue(true);
    }

    public function testValidateFilterReturnsEarlyWhenEmpty(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        Pagination::validateFilter([], $context, null);
    }

    public function testValidateFilterAcceptsZeroStringValue(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        Pagination::validateFilter(
            [
                'eq:isVerified' => '0',
            ],
            $context,
            null
        );
    }

    public function testValidateFilterThrowsWhenNotArray(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        Pagination::validateFilter('invalid', $this->createContextMock(), null);
    }

    public function testValidateSortRejectsInvalidDirection(): void
    {
        $context = $this->createContextMockExpectingViolations();

        Pagination::validateSort(
            [
                'domain' => 'sideways',
            ],
            $context,
            null
        );

        self::assertTrue(true);
    }

    public function testValidateSortThrowsWhenNotArray(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        Pagination::validateSort('invalid', $this->createContextMock(), null);
    }

    public function testValidateSortAcceptsValidDirection(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        Pagination::validateSort(
            [
                'domain' => 'asc',
            ],
            $context,
            null
        );
    }

    private function createContextMock(): ExecutionContextInterface
    {
        $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->method('addViolation')->willReturnCallback(static function (): void {});

        $context = $this->createStub(ExecutionContextInterface::class);
        $context->method('buildViolation')->willReturn($builder);

        return $context;
    }

    private function createContextMockExpectingViolations(): ExecutionContextInterface
    {
        $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->method('addViolation')->willReturnCallback(static function (): void {});

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::atLeastOnce())->method('buildViolation')->willReturn($builder);

        return $context;
    }
}
