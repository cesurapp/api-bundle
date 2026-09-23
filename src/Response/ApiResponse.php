<?php

namespace Cesurapp\ApiBundle\Response;

use Cesurapp\ApiBundle\Doctrine\QueryPaginator;
use Cesurapp\ApiBundle\Response\Traits\DoctrineFilterTrait;
use Cesurapp\ApiBundle\Response\Traits\ExportTrait;
use Cesurapp\ApiBundle\Response\Traits\FileDownloadTrait;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Symfony Global Response | Paginator.
 */
class ApiResponse
{
    use DoctrineFilterTrait;
    use ExportTrait;
    use FileDownloadTrait;

    /** symfony/uid is optional: a UUID/ULID in the data is a value, not a resource item. */
    private const string UID_CLASS = 'Symfony\Component\Uid\AbstractUid';

    private int $code = 200;
    private array $headers = [];
    private mixed $data = [];
    private array $options = [];
    private ?string $resource = null;
    private mixed $resourceOptionalData = null;
    private Query|QueryBuilder|null $query = null;

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): self
    {
        if (is_array($data)) {
            $this->data = $data;
        } else {
            $this->data = ['data' => $data];
        }

        return $this;
    }

    public function addData(string $key, mixed $value, bool $appendRoot = false): self
    {
        if ($appendRoot) {
            $this->data[$key] = $value;
        } else {
            if (!isset($this->data['data'])) {
                $this->data['data'] = [];
            }
            $this->data['data'][$key] = $value;
        }

        return $this;
    }

    public function getQuery(): Query|QueryBuilder|null
    {
        return $this->query;
    }

    /**
     * Filtering, sorting, cursor pagination and export need a QueryBuilder. A Query is paginated
     * by offset and exported as is; `filter[]` / `sort_by` are ignored for it.
     */
    public function setQuery(Query|QueryBuilder $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function isPaginate(): bool
    {
        return isset($this->options['pager']) && null !== $this->query;
    }

    public function getPaginate(): ?array
    {
        return $this->options['pager'] ?? null;
    }

    /**
     * @param int|null  $max       default page size (?max= can lower it or raise it up to $maxQuery); null = $maxQuery
     * @param bool|null $fetchJoin null = detect: true only when a join can repeat a root row (to-many or unknown join)
     */
    public function setPaginate(?int $max = 20, bool $total = false, ?bool $fetchJoin = null, bool $cursor = false, int $maxQuery = 100): self
    {
        $maxQuery = max(1, $maxQuery);

        $this->options['pager'] = [
            'type' => $cursor ? 'Cursor' : 'Offset',
            'max' => min(max(1, $max ?? $maxQuery), $maxQuery),
            'maxQuery' => $maxQuery,
            'total' => $total,
            'fetchJoin' => $fetchJoin,
        ];

        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resourceClass, mixed $optionalData = null): self
    {
        $this->resource = $resourceClass;
        $this->resourceOptionalData = $optionalData;

        return $this;
    }

    public function setCorsOrigin(string $domain): self
    {
        $this->headers['Access-Control-Allow-Origin'] = $domain;

        return $this;
    }

    public function getHTTPCache(): ?array
    {
        return $this->options['httpCache'] ?? null;
    }

    /**
     * Enable HTTP Proxy Cache.
     */
    public function setHTTPCache(int $lifetime = 60, ?array $tags = null): self
    {
        $this->options['httpCache'] = [
            'public' => true,
            'max_age' => $lifetime,
            's_maxage' => $lifetime,
        ];

        if ($tags) {
            $this->headers['Cache-Tag'] = implode(',', array_map(static fn ($tag) => hash('xxh3', (string) $tag), $tags));
        }

        $this->headers[AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER] = true;

        return $this;
    }

    /**
     * Max rows of an export (?export=csv|xls) of this response; null = the `api.export_max_rows` default, 0 = unlimited.
     */
    public function setExportLimit(?int $limit): self
    {
        $this->options['exportLimit'] = $limit;

        return $this;
    }

    public function getOptions(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    /**
     * Translate route.
     *
     * @param $message string #TranslationKey to translate the URL for
     */
    public function addMessage(string $message, MessageType $messageType = MessageType::SUCCESS): self
    {
        if (!isset($this->data['message'][$messageType->value])) {
            $this->data['message'][$messageType->value] = [];
        }

        $this->data['message'][$messageType->value][] = $message;

        return $this;
    }

    public static function create(int $code = 200): self
    {
        return new self()->setCode($code);
    }

    /**
     * Process Object Array Serialize.
     *
     * @param int|null $exportMaxRows default row limit of an export (`api.export_max_rows`), null/0 = unlimited
     */
    public function processResponse(
        Request $request,
        ApiResourceLocator $resLocator,
        TranslatorInterface $trans,
        ?int $exportMaxRows = null,
    ): JsonResponse|StreamedResponse {
        if ($this->resource) {
            $res = $resLocator->getResource($this->resource);

            // Process Query Filter (a cursor page is always ordered by id)
            if ($this->query instanceof QueryBuilder) {
                $this->filterQueryBuilder($this->query, $request, $res, !$this->isCursorPaginate());
            }

            // Process Export
            if (null !== $this->query && $this->isExport($request, $res)) {
                return $this->exportStream($this->query, $request, $res, $this->options['exportLimit'] ?? $exportMaxRows);
            }
        }

        // Init Paginator
        if ($this->isPaginate()) {
            $this->paginate($request);
        }

        // Process Resource
        if ($this->resource) {
            $this->transformResources($resLocator->get($this->resource));
        }

        // Message Translator
        if (isset($this->data['message'])) {
            foreach ($this->data['message'] as $type => $messages) {
                $this->data['message'][$type] = array_map(static fn ($msg) => $trans->trans($msg), $messages);
            }
        }

        // Create JSON Response
        $response = new JsonResponse($this->getData(), $this->getCode(), $this->getHeaders());
        if ($this->getHTTPCache()) {
            $response->setCache($this->getHTTPCache());
        }

        return $response;
    }

    /**
     * Every object in the data (at any depth) that is a resource item goes through the resource.
     * Value objects — dates, enums, UUIDs — are left for json_encode.
     */
    private function transformResources(ApiResourceInterface $resource): void
    {
        $isItem = static fn (mixed $value): bool => is_object($value)
            && !$value instanceof \DateTimeInterface
            && !$value instanceof \UnitEnum
            && !is_a($value, self::UID_CLASS);

        if ($resource instanceof ApiResourcePreloadInterface) {
            $items = [];
            array_walk_recursive($this->data, static function ($value) use (&$items, $isItem) {
                if ($isItem($value)) {
                    $items[] = $value;
                }
            });

            if ($items) {
                $resource->preload($items, $this->resourceOptionalData);
            }
        }

        array_walk_recursive($this->data, function (&$value) use ($resource, $isItem) {
            if ($isItem($value)) {
                $value = $resource->toArray($value, $this->resourceOptionalData);
            }
        });
    }

    private function isCursorPaginate(): bool
    {
        return 'Cursor' === ($this->options['pager']['type'] ?? null);
    }

    /**
     * Paginate Query to Offset.
     *
     * ?page=1
     * ?page=3
     */
    private function paginate(Request $request): void
    {
        $config = $this->getPaginate();
        $query = $this->getQuery();
        if (null === $config || null === $query) {
            return;
        }

        $max = $request->query->getInt('max', $config['max']);
        $max = $max < 1 ? $config['max'] : min($max, $config['maxQuery']);

        if ($this->isCursorPaginate()) {
            if (!$query instanceof QueryBuilder) {
                throw new \LogicException('Cursor pagination requires a QueryBuilder.');
            }

            $this->paginateCursor($query, $request, $config, $max);

            return;
        }

        // page < 1 would be a negative OFFSET; a page past PHP_INT_MAX rows overflows it
        $page = min(max(1, $request->query->getInt('page', 1)), intdiv(PHP_INT_MAX, $max + 1));

        // Paginate: max + 1 rows tell whether a next page exists, without a COUNT query
        $paginator = new QueryPaginator($config['fetchJoin'] ?? $this->needsDistinctPaging($query));
        $items = $paginator->items($query, ($page - 1) * $max, $max + 1);

        $pager = [
            'max' => $max,
            'prev' => $page > 1 ? $page - 1 : null,
            'next' => count($items) > $max ? $page + 1 : null,
            'current' => $page,
        ];

        if ($config['total']) {
            $pager['total'] = $paginator->count($query);
        }

        // Append Pager Data
        $this->addData('data', array_slice($items, 0, $max), true);
        $this->addData('pager', $pager, true);
    }

    /**
     * Paginate Query to Cursor, on the root entity's identifier.
     *
     * ?cursor=<lastId>&sort=DESC
     */
    private function paginateCursor(QueryBuilder $query, Request $request, array $config, int $max): void
    {
        $alias = $query->getRootAliases()[0];
        $em = $query->getEntityManager();
        $meta = $em->getClassMetadata($query->getRootEntities()[0]);
        $idField = $meta->getSingleIdentifierFieldName();
        $idType = $meta->getTypeOfField($idField) ?? 'string';
        $sortBy = 'DESC' === strtoupper((string) $request->query->get('sort', 'DESC')) ? 'DESC' : 'ASC';

        // Set Cursor Order
        $cursor = $request->query->get('cursor');
        if (null !== $cursor && '' !== $cursor) {
            $this->assertCursor((string) $cursor, $idType, $em->getConnection()->getDatabasePlatform());

            $operator = 'DESC' === $sortBy ? '<' : '>';
            $query->andWhere("$alias.$idField $operator :__cursor")->setParameter('__cursor', (string) $cursor, $idType);
        }

        $query->orderBy("$alias.$idField", 'DESC' === $sortBy ? \SortDirection::Descending : \SortDirection::Ascending);

        $paginator = new QueryPaginator($config['fetchJoin'] ?? $this->needsDistinctPaging($query));
        $results = $paginator->items($query, 0, $max + 1);
        $hasMore = count($results) > $max;
        $data = array_slice($results, 0, $max);

        $this->addData('data', $data, true);
        $this->addData('pager', [
            'max' => $max,
            'next' => $hasMore && $data ? self::cursorOf(end($data), $meta, $idField) : null,
            'sort' => $sortBy,
        ], true);
    }

    /**
     * A malformed cursor is the client's mistake (400), not a database error (500).
     */
    private function assertCursor(string $cursor, string $idType, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): void
    {
        $valid = match ($idType) {
            'integer', 'bigint', 'smallint' => 1 === preg_match('/^-?\d{1,19}$/', $cursor),
            'guid' => 1 === preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $cursor),
            default => true,
        };

        if ($valid && Type::hasType($idType)) {
            try {
                Type::getType($idType)->convertToDatabaseValue($cursor, $platform);
            } catch (\Throwable) {
                $valid = false;
            }
        }

        if (!$valid) {
            throw new BadRequestHttpException('Invalid cursor.');
        }
    }

    private static function cursorOf(mixed $row, ClassMetadata $meta, string $idField): ?string
    {
        // A mixed result row is [0 => entity, 'scalar' => …]
        if (is_array($row) && isset($row[0]) && is_object($row[0])) {
            $row = $row[0];
        }

        $id = is_object($row) ? $meta->getFieldValue($row, $idField) : ($row[$idField] ?? null);

        return match (true) {
            null === $id => null,
            is_scalar($id), $id instanceof \Stringable => (string) $id,
            default => null,
        };
    }

    /**
     * Whether a LIMIT on the SQL rows could cut a root entity's rows apart: any to-many join, or a
     * join that cannot be resolved to a to-one association (arbitrary joins). Only then does the
     * Paginator need its costlier distinct-id query.
     */
    private function needsDistinctPaging(Query|QueryBuilder $query): bool
    {
        if ($query instanceof Query) {
            return true;
        }

        $joins = $query->getDQLPart('join');
        if (!$joins) {
            return false;
        }

        $em = $query->getEntityManager();
        $aliases = array_combine($query->getRootAliases(), $query->getRootEntities());

        foreach ($joins as $rootJoins) {
            foreach ($rootJoins as $join) {
                if (!preg_match('/^(\w+)\.(\w+)$/', $join->getJoin(), $m) || !isset($aliases[$m[1]])) {
                    return true;
                }

                $meta = $em->getClassMetadata($aliases[$m[1]]);
                if (!$meta->hasAssociation($m[2]) || $meta->isCollectionValuedAssociation($m[2])) {
                    return true;
                }

                $aliases[$join->getAlias()] = $meta->getAssociationTargetClass($m[2]);
            }
        }

        return false;
    }
}
