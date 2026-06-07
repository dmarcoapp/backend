<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\Input\Common\Pagination;
use App\Enum\Common\FiltrationOperator;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

trait PaginationAwareTrait
{
    private function paginate(
        Pagination $pagination,
        ?QueryBuilder $queryBuilder = null,
        array $context = [],
    ): Paginator {
        if (null === $queryBuilder) {
            if (method_exists($this, 'getListingQueryBuilder')) {
                $queryBuilder = $this->getListingQueryBuilder($context);
            } else {
                throw new \BadMethodCallException('The getListingQueryBuilder() method does not exist in class '.get_class($this));
            }
        }

        return $this->doPaginate($pagination, $queryBuilder);
    }

    private function doPaginate(
        Pagination $pagination,
        QueryBuilder $queryBuilder,
    ): Paginator {
        $queryBuilder
            ->setFirstResult(($pagination->page - 1) * $pagination->pageSize)
            ->setMaxResults($pagination->pageSize)
        ;

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $class = $this->getClassName();
        $entityManager = $queryBuilder->getEntityManager();
        $metadata = $entityManager->getClassMetadata($class);
        $joins = [];

        if (!empty($pagination->filter)) {
            foreach ($pagination->filter as $key => $value) {
                [$rawOperator, $field] = explode(':', $key);

                if (!in_array($field, $class::getAvailableFilterFields(), true)) {
                    continue;
                }

                $operator = FiltrationOperator::from($rawOperator); // TODO: restrict operators per field

                $alias = sprintf('%s.%s', $rootAlias, $field);
                $sqlOperator = '=';
                $filterParamTemplate = ':filter_param_%s';
                $sqlFilterParamTemplate = $filterParamTemplate;
                $sqlValue = $value;
                $parameterKey = $field;

                if (str_contains($field, '.')) {
                    if (substr_count($field, '.') > 1) {
                        continue;
                    }

                    [$relation, $relatedField] = explode('.', $field, 2);

                    if (!$metadata->hasAssociation($relation) || '' === $relatedField) {
                        continue;
                    }

                    if (!array_key_exists($relation, $joins)) {
                        $relationAlias = sprintf('%s_%s', $rootAlias, $relation);
                        $joins[$relation] = $relationAlias;

                        $queryBuilder->innerJoin(sprintf('%s.%s', $rootAlias, $relation), $relationAlias);

                        if ($metadata->isCollectionValuedAssociation($relation)) {
                            $queryBuilder->distinct(true);
                        }
                    }

                    $alias = sprintf('%s.%s', $joins[$relation], $relatedField);
                    $parameterKey = str_replace('.', '_', $field);
                }

                switch ($operator) {
                    case FiltrationOperator::NOT_EQUALS:
                        $sqlOperator = '!=';

                        break;

                    case FiltrationOperator::CONTAINS:
                        $alias = sprintf('LOWER(%s)', $alias);
                        $sqlOperator = 'LIKE';
                        $sqlValue = '%'.mb_strtolower($value).'%';

                        break;

                    case FiltrationOperator::NOT_CONTAINS:
                        $alias = sprintf('LOWER(%s)', $alias);
                        $sqlOperator = 'NOT LIKE';
                        $sqlValue = '%'.mb_strtolower($value).'%';

                        break;

                    case FiltrationOperator::IN:
                        $sqlOperator = 'IN';
                        $sqlFilterParamTemplate = sprintf('(%s)', $sqlFilterParamTemplate);
                        $sqlValue = explode(',', $value);

                        break;

                    case FiltrationOperator::NOT_IN:
                        $sqlOperator = 'NOT IN';
                        $sqlFilterParamTemplate = sprintf('(%s)', $sqlFilterParamTemplate);
                        $sqlValue = explode(',', $value);

                        break;

                    case FiltrationOperator::LOWER_THAN:
                        $sqlOperator = '<';

                        break;

                    case FiltrationOperator::LOWER_EQUALS:
                        $sqlOperator = '<=';

                        break;

                    case FiltrationOperator::GREATER_THAN:
                        $sqlOperator = '>';

                        break;

                    case FiltrationOperator::GREATER_EQUALS:
                        $sqlOperator = '>=';

                        break;
                }

                $queryBuilder->andWhere(sprintf('%s %s '.$sqlFilterParamTemplate, $alias, $sqlOperator, $parameterKey))
                    ->setParameter(sprintf($filterParamTemplate, $parameterKey), $sqlValue)
                ;
            }
        }

        if (!empty($pagination->sort)) {
            foreach ($pagination->sort as $field => $direction) {
                if (!in_array($field, $class::getAvailableSortFields(), true)) {
                    continue;
                }
                $queryBuilder->addOrderBy(sprintf('%s.%s', $rootAlias, $field), $direction);
            }
        } else {
            $queryBuilder->orderBy(sprintf('%s.id', $rootAlias), 'ASC');
        }

        return new Paginator($queryBuilder->getQuery());
    }
}
