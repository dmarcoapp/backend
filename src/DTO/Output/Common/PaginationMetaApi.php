<?php

declare(strict_types=1);

namespace App\DTO\Output\Common;

use App\Entity\FilterableInterface;
use App\Entity\SortableInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

final readonly class PaginationMetaApi
{
    public int $pageCount;

    public int $totalCount;

    /**
     * @var string[]
     */
    public array $filterableFields;

    /**
     * @var string[]
     */
    public array $sortableFields;

    /**
     * @param class-string $class
     */
    public function __construct(
        Paginator $paginator,
        string $class,
    ) {
        $this->pageCount = (int) ceil($paginator->count() / $paginator->getQuery()->getMaxResults());
        $this->totalCount = $paginator->count();
        $this->filterableFields = in_array(FilterableInterface::class, class_implements($class), true)
            ? $class::getAvailableFilterFields()
            : [];
        $this->sortableFields = in_array(SortableInterface::class, class_implements($class), true)
            ? $class::getAvailableSortFields()
            : [];
    }
}
