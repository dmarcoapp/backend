<?php

declare(strict_types=1);

namespace App\DTO\Input\Common;

use App\Enum\Common\FiltrationOperator;
use App\Enum\Common\SortDirection;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final readonly class Pagination
{
    /**
     * @param array<string, string> $filter
     * @param array<string, string> $sort
     */
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Choice(choices: [5, 10, 25, 50])]
        public int $pageSize = 50,
        #[OA\Property(
            description: 'Associative array of filters. The filter key consists of a filter type and a field name separated by a colon. Available filter types are: eq, neq, contains, not_contains, in, not_in, lt, lte, gt, gte. Example: ?filter[eq:field1]=example.com&filter[in:field2]=example.com,test.com',
        )]
        #[Assert\Type('array')]
        #[Assert\Callback([self::class, 'validateFilter'])]
        public array $filter = [],
        #[OA\Property(
            description: 'Associative array of sorts. Example: sort[domain]=desc',
        )]
        #[Assert\Type('array')]
        #[Assert\Count(min: 0, max: 1)]
        #[Assert\Callback([self::class, 'validateSort'])]
        public array $sort = [],
    ) {}

    public static function validateFilter(mixed $value, ExecutionContextInterface $context, mixed $payload): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_array($value)) {
            // throw this exception if your validator cannot handle the passed type so that it can be marked as invalid
            throw new UnexpectedTypeException($value, 'array');
        }

        foreach ($value as $key => $val) {
            $isValidKey = is_string($key) && '' !== $key;
            $isValidValue = is_scalar($val) && (!is_string($val) || '' !== $val);

            if (!$isValidKey || !$isValidValue) {
                $context->buildViolation('The filter must be an array of key-value pairs.')
                    ->atPath('filter')
                    ->addViolation()
                ;
            }

            if (!$isValidKey) {
                continue;
            }

            if (!str_contains($key, ':')) {
                $context->buildViolation('The filter keys must be in the format "operator:field".')
                    ->atPath('filter')
                    ->addViolation()
                ;

                continue;
            }

            [$rawOperator, $field] = explode(':', $key, 2);
            if (!FiltrationOperator::tryFrom($rawOperator)) {
                $context->buildViolation(sprintf('The filter operator must be one of %s.', implode(', ', array_map(fn (FiltrationOperator $operator) => $operator->value, FiltrationOperator::cases()))))
                    ->atPath('filter')
                    ->addViolation()
                ;
            }

            if ('' === $field) {
                $context->buildViolation('The filter field cannot be empty.')
                    ->atPath('filter')
                    ->addViolation()
                ;
            }
        }
    }

    public static function validateSort(mixed $value, ExecutionContextInterface $context, mixed $payload): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_array($value)) {
            // throw this exception if your validator cannot handle the passed type so that it can be marked as invalid
            throw new UnexpectedTypeException($value, 'array');
        }

        $validDirections = array_map(fn (SortDirection $direction) => $direction->value, SortDirection::cases());

        foreach ($value as $key => $val) {
            if (empty($key) || !is_string($key) || empty($val) || !in_array($val, $validDirections, true)) {
                $context->buildViolation('The sort must be an array of key-value pairs with values "asc" or "desc".')
                    ->atPath('sort')
                    ->addViolation()
                ;
            }
        }
    }
}
