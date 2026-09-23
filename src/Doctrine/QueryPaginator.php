<?php

namespace Cesurapp\ApiBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\CountOutputWalker;
use Doctrine\ORM\Tools\Pagination\CountWalker;
use Doctrine\ORM\Tools\Pagination\LimitSubqueryOutputWalker;
use Doctrine\ORM\Tools\Pagination\LimitSubqueryWalker;
use Doctrine\ORM\Tools\Pagination\RootTypeWalker;
use Doctrine\ORM\Tools\Pagination\WhereInWalker;

/**
 * A window of a DQL query, and its total only when asked for.
 *
 * ApiResponse reads max+1 rows to know whether a next page exists and never counts unless the
 * response asks for a total. Doctrine's Paginator did exactly that, but it is deprecated in ORM
 * 3.7 and removed in 4.0, and its replacement OffsetPaginator always runs the COUNT query. This
 * keeps the old behaviour on the public pagination walkers that ORM 3.7 and 4.0 both ship — the
 * same strategy as ORM's own paginators: with a fetch-joined collection the window is taken over
 * distinct root ids, then those entities are loaded WHERE id IN (…).
 */
final readonly class QueryPaginator
{
    /**
     * @param bool      $fetchJoinCollection whether a join can repeat a root row (to-many)
     * @param bool|null $useOutputWalkers    null = decide per query (Doctrine's default)
     */
    public function __construct(
        private bool $fetchJoinCollection = true,
        private ?bool $useOutputWalkers = null,
    ) {
    }

    /**
     * Rows $offset … $offset + $limit - 1, as a list.
     *
     * @return list<mixed>
     */
    public function items(Query|QueryBuilder $query, int $offset, int $limit): array
    {
        $query = $query instanceof QueryBuilder ? $query->getQuery() : $query;

        if (!$this->fetchJoinCollection) {
            $result = $this->cloneQuery($query)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->setCacheable($query->isCacheable())
                ->getResult($query->getHydrationMode());

            return is_array($result) ? array_values($result) : [];
        }

        $idQuery = $this->cloneQuery($query);
        if ($this->useOutputWalker($idQuery)) {
            $idQuery->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LimitSubqueryOutputWalker::class);
        } else {
            $this->appendTreeWalker($idQuery, LimitSubqueryWalker::class);
            $this->unbindUnusedQueryParams($idQuery);
        }
        $idQuery->setFirstResult($offset)->setMaxResults($limit);

        $ids = array_map('current', $idQuery->getScalarResult());
        if ([] === $ids) {
            return [];
        }

        $whereInQuery = $this->cloneQuery($query);
        $this->appendTreeWalker($whereInQuery, WhereInWalker::class);
        $whereInQuery->setHint(WhereInWalker::HINT_PAGINATOR_HAS_IDS, true);
        $whereInQuery->setFirstResult(0)->setMaxResults(null);
        $whereInQuery->setCacheable($query->isCacheable());
        $whereInQuery->setParameter(WhereInWalker::PAGINATOR_ID_ALIAS, $this->toDatabaseIds($query, $ids));

        $result = $whereInQuery->getResult($query->getHydrationMode());

        return is_array($result) ? array_values($result) : [];
    }

    /**
     * Number of root entities matching the query, ignoring any window.
     */
    public function count(Query|QueryBuilder $query): int
    {
        $query = $query instanceof QueryBuilder ? $query->getQuery() : $query;

        try {
            return (int) array_sum(array_map('current', $this->countQuery($query)->getScalarResult()));
        } catch (NoResultException) {
            return 0;
        }
    }

    private function countQuery(Query $query): Query
    {
        // A fresh query: the SELECT is replaced, so a result set mapping of the original must not carry over
        $countQuery = $query->getEntityManager()->createQuery((string) $query->getDQL());
        $countQuery->setParameters(clone $query->getParameters());
        $countQuery->setCacheable(false);
        foreach ($query->getHints() as $name => $value) {
            $countQuery->setHint($name, $value);
        }

        if (!$countQuery->hasHint(CountWalker::HINT_DISTINCT)) {
            $countQuery->setHint(CountWalker::HINT_DISTINCT, true);
        }

        if ($this->useOutputWalker($countQuery)) {
            $platform = $countQuery->getEntityManager()->getConnection()->getDatabasePlatform();
            $rsm = new ResultSetMapping();
            $rsm->addScalarResult(self::resultColumn($platform, 'dctrn_count'), 'count');
            $countQuery->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, CountOutputWalker::class);
            $countQuery->setResultSetMapping($rsm);
        } else {
            $this->appendTreeWalker($countQuery, CountWalker::class);
            $this->unbindUnusedQueryParams($countQuery);
        }

        return $countQuery->setFirstResult(0)->setMaxResults(null);
    }

    private function useOutputWalker(Query $query): bool
    {
        return $this->useOutputWalkers ?? false === (bool) $query->getHint(Query::HINT_CUSTOM_OUTPUT_WALKER);
    }

    private function cloneQuery(Query $query): Query
    {
        $clone = clone $query;
        $clone->setParameters(clone $query->getParameters());
        $clone->setCacheable(false);
        foreach ($query->getHints() as $name => $value) {
            $clone->setHint($name, $value);
        }

        return $clone;
    }

    /**
     * @param class-string $walker
     */
    private function appendTreeWalker(Query $query, string $walker): void
    {
        $walkers = $query->getHint(Query::HINT_CUSTOM_TREE_WALKERS);
        $walkers = is_array($walkers) ? $walkers : [];
        $walkers[] = $walker;

        $query->setHint(Query::HINT_CUSTOM_TREE_WALKERS, $walkers);
    }

    /**
     * A tree walker rewrites the query; parameters it dropped would fail the execution.
     */
    private function unbindUnusedQueryParams(Query $query): void
    {
        $mappings = new Parser($query)->parse()->getParameterMappings();
        $parameters = $query->getParameters();

        foreach ($parameters as $key => $parameter) {
            if (!array_key_exists($parameter->getName(), $mappings)) {
                unset($parameters[$key]);
            }
        }

        $query->setParameters($parameters);
    }

    /**
     * Ids read by the id query, converted back to their database form (binary UUIDs…).
     *
     * @param array<mixed> $ids
     *
     * @return array<mixed>
     */
    private function toDatabaseIds(Query $query, array $ids): array
    {
        $typeQuery = $this->cloneQuery($query);
        $typeQuery->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, RootTypeWalker::class);
        $type = $typeQuery->getSQL();
        if (!is_string($type)) {
            return $ids;
        }

        $connection = $query->getEntityManager()->getConnection();

        return array_map(static fn ($id) => $connection->convertToDatabaseValue($id, $type), $ids);
    }

    private static function resultColumn(AbstractPlatform $platform, string $column): string
    {
        return match (true) {
            $platform instanceof DB2Platform, $platform instanceof OraclePlatform => strtoupper($column),
            $platform instanceof PostgreSQLPlatform => strtolower($column),
            default => $column,
        };
    }
}
