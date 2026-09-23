<?php

namespace Cesurapp\ApiBundle\Response\Traits;

use Cesurapp\ApiBundle\Doctrine\DoctrineHelper;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait DoctrineFilterTrait
{
    /**
     * Filter QueryBuilder.
     *
     * filter[name]=acme
     * filter[id]=22
     */
    private function filterQueryBuilder(QueryBuilder $builder, Request $request, array $resource, bool $sortable = true): void
    {
        if ($sortable) {
            $this->sortResult($builder, $request, $resource);
        }

        if (!$request->query->has('filter')) {
            return;
        }

        $alias = $builder->getRootAliases()[0];
        $data = $request->query->all('filter');

        foreach ($this->getFilters($resource) as $columnId => $config) {
            if (!isset($data[$columnId])) {
                continue;
            }

            if (!is_array($config['filter'])) {
                $this->applyFilter($config['filter'], $builder, $alias, $data[$columnId], (string) $columnId);
                continue;
            }

            // Range filter (filter[createdAt][from]=…): a plain value has no parts to apply
            if (!is_array($data[$columnId])) {
                continue;
            }

            foreach ($config['filter'] as $id => $filter) {
                if (isset($data[$columnId][$id])) {
                    $this->applyFilter($filter, $builder, $alias, $data[$columnId][$id], $columnId.'['.$id.']');
                }
            }
        }

        DoctrineHelper::setUniqueJoin($builder);
    }

    /**
     * A value the filter cannot take (filter[email][]=x for a `string $data` filter) or rejects
     * itself (InvalidArgumentException: "Invalid UUID") is a bad request, not a server error.
     */
    private function applyFilter(callable $filter, QueryBuilder $builder, string $alias, mixed $value, string $name): void
    {
        if (!self::filterAccepts($filter, $value)) {
            throw new BadRequestHttpException(sprintf('Invalid value for filter "%s".', $name));
        }

        try {
            $filter($builder, $alias, $value);
        } catch (\InvalidArgumentException|\ValueError $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }

    private static function filterAccepts(callable $filter, mixed $value): bool
    {
        $type = (new \ReflectionFunction(\Closure::fromCallable($filter))->getParameters()[2] ?? null)?->getType();
        if (null === $type) {
            return true;
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $member) {
            $accepts = $member instanceof \ReflectionNamedType && match ($member->getName()) {
                'mixed' => true,
                'array', 'iterable' => is_array($value),
                'string', 'bool' => is_scalar($value),
                'int', 'float' => is_numeric($value),
                default => false,
            };

            if ($accepts) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort QueryBuilder.
     *
     * ?sort=ASC
     * ?sort_by=id
     */
    private function sortResult(QueryBuilder $builder, Request $request, array $resource): void
    {
        $sortBy = $request->query->get('sort_by');
        $resource = $sortBy ? ($resource[$sortBy]['table'] ?? []) : [];

        if (!$sortBy || empty($resource['sortable'])) {
            return;
        }

        // Generate Direction ('ASC' / 'DESC' for a sortable_field callable, SortDirection for Doctrine)
        $direction = match (strtoupper((string) $request->query->get('sort', ''))) {
            'ASC' => 'ASC',
            default => 'DESC',
        };
        $order = 'ASC' === $direction ? \SortDirection::Ascending : \SortDirection::Descending;

        $alias = $builder->getRootAliases()[0];
        $sortField = $resource['sortable_field'] ?? $sortBy;

        // A string is a field name even when a global function has that name ("date", "max")
        if ($sortField instanceof \Closure || (is_callable($sortField) && !is_string($sortField))) {
            $sortField($builder, $alias, $direction);
        } else {
            $sortField = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $sortField))));
            $builder->orderBy($alias.'.'.$sortField, $order);
        }

        $this->addIdTieBreaker($builder, $alias, $order);
    }

    /**
     * Rows with an equal sort value have no order of their own, so OFFSET pages could repeat or
     * skip them. The identifier makes the order total.
     */
    private function addIdTieBreaker(QueryBuilder $builder, string $alias, \SortDirection $order): void
    {
        try {
            $idField = $builder->getEntityManager()->getClassMetadata($builder->getRootEntities()[0])->getSingleIdentifierFieldName();
        } catch (MappingException) {
            return; // composite identifier
        }

        $idExpr = $alias.'.'.$idField;
        foreach ($builder->getDQLPart('orderBy') as $orderBy) {
            foreach ($orderBy->getParts() as $part) {
                if (preg_match('/^'.preg_quote($idExpr, '/').'(\s|$)/', trim($part))) {
                    return;
                }
            }
        }

        $builder->addOrderBy($idExpr, $order);
    }

    private function getFilters(array $resource): array
    {
        return array_filter($resource, static fn ($v) => isset($v['filter']));
    }
}
