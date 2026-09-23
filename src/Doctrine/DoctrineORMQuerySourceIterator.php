<?php

namespace Cesurapp\ApiBundle\Doctrine;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\QueryException;
use Sonata\Exporter\Source\AbstractPropertySourceIterator;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * Streams a query for export without keeping every row in the identity map: each exported root
 * entity is detached once written. Only those rows are detached — never the whole EntityManager,
 * which would also detach the entities the rest of the request (security user, listeners) holds.
 */
class DoctrineORMQuerySourceIterator extends AbstractPropertySourceIterator
{
    protected Query $query;

    /**
     * @param array<string> $fields Fields to export
     * @param int|null      $limit  Max rows, null = unlimited
     */
    public function __construct(
        Query $query,
        array $fields,
        private readonly array $fieldTemplate,
        string $dateTimeFormat = 'r',
        private readonly int $batchSize = 100,
        private readonly ?int $limit = null,
    ) {
        $this->query = $this->copyQuery($query);

        parent::__construct($fields, $dateTimeFormat);
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $current = $this->getIterator()->current();

        $data = $this->getCurrentData($current);

        $entity = is_array($current) ? ($current[0] ?? null) : $current;
        if (is_object($entity) && $this->query->getEntityManager()->contains($entity)) {
            $this->query->getEntityManager()->detach($entity);
        }

        return $data;
    }

    public function rewind(): void
    {
        $query = $this->copyQuery($this->query);
        if ($this->limit) {
            $query->setMaxResults($this->limit);
        }

        // toIterable() cannot hydrate a fetch-joined collection; page through it instead
        try {
            $iterator = $this->toIterator($query->toIterable());
            $iterator->rewind();
        } catch (QueryException) {
            $iterator = $this->batches();
            $iterator->rewind();
        }

        $this->iterator = $iterator;
    }

    private function toIterator(iterable $iterable): \Iterator
    {
        return match (true) {
            $iterable instanceof \Iterator => $iterable,
            $iterable instanceof \IteratorAggregate => $this->toIterator($iterable->getIterator()),
            default => new \ArrayIterator(is_array($iterable) ? $iterable : iterator_to_array($iterable)),
        };
    }

    /**
     * Fetch-joined collections: pages of $batchSize root entities (distinct ids first).
     */
    private function batches(): \Generator
    {
        $offset = 0;
        do {
            $size = $this->limit ? min($this->batchSize, $this->limit - $offset) : $this->batchSize;
            if ($size < 1) {
                return;
            }

            $rows = new QueryPaginator(true)->items($this->query, $offset, $size);
            foreach ($rows as $row) {
                yield $row;
            }

            $offset += count($rows);
        } while (count($rows) === $size);
    }

    private function copyQuery(Query $query): Query
    {
        $copy = clone $query;
        $copy->setParameters($query->getParameters());
        foreach ($query->getHints() as $name => $value) {
            $copy->setHint($name, $value);
        }

        return $copy;
    }

    protected function getCurrentData(object|array $current): array
    {
        $data = [];
        foreach ($this->fields as $key => $field) {
            $name = \is_string($key) ? $key : $field;
            $propertyPath = $field;

            try {
                $val = $this->propertyAccessor->getValue($current, new PropertyPath($propertyPath));

                $data[$name] = isset($this->fieldTemplate[$name]['exporter']) ? $this->fieldTemplate[$name]['exporter']($val) : $this->getValue($val);
            } catch (UnexpectedTypeException) {
                // Non existent object in path will be ignored but a wrong path will still throw exceptions
                $data[$name] = null;
            }
        }

        return $data;
    }
}
