<?php

namespace Cesurapp\ApiBundle\Response\Traits;

use Cesurapp\ApiBundle\Doctrine\DoctrineORMQuerySourceIterator;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Sonata\Exporter\Writer\CsvWriter;
use Sonata\Exporter\Writer\XlsWriter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportTrait
{
    private function getAll(Request $request, string $key, bool|float|int|string|null $default = null): mixed
    {
        return $request->query->get($key, $default) ?? $request->request->get($key, $default);
    }

    private function getArray(Request $request, string $key): array
    {
        $f = $request->query->all($key);

        return $f ?: $request->request->all($key);
    }

    /**
     * Export is offered by paginated list responses only — the same ones Thor documents it for.
     */
    private function isExport(Request $request, array $resource): bool
    {
        return null !== $this->query
            && $this->isPaginate()
            && $this->getAll($request, 'export')
            && array_any($resource, static fn ($v) => isset($v['table']));
    }

    /**
     * Export to XLS | Csv.
     *
     * @param int|null $limit max rows; null/0 = unlimited
     */
    private function exportStream(QueryBuilder|Query $builder, Request $request, array $resource, ?int $limit = null): StreamedResponse
    {
        $resource = array_filter($resource, static fn ($v) => isset($v['table']));

        // export_field[] is matched case-insensitively (ID → id, createdat → createdAt)
        $keys = [];
        foreach (array_keys($resource) as $key) {
            $keys[strtolower((string) $key)] = $key;
        }
        $fields = [];
        foreach ($this->getArray($request, 'export_field') as $field) {
            if (is_string($field) && isset($keys[strtolower($field)])) {
                $fields[] = $keys[strtolower($field)];
            }
        }
        $fields = array_values(array_unique($fields)) ?: array_keys($resource);

        // Source
        $source = new DoctrineORMQuerySourceIterator(
            $builder instanceof QueryBuilder ? $builder->getQuery() : $builder,
            $fields,
            array_map(static fn ($v) => $v['table'] ?? [], $resource),
            limit: $limit ?: null,
        );

        // Writer
        $writer = match ($this->getAll($request, 'export')) {
            'xls' => new XlsWriter('php://output'),
            default => new CsvWriter('php://output'),
        };

        // Response
        return new StreamedResponse(static function () use ($source, $writer, $resource) {
            $writer->open();

            foreach ($source as $index => $data) {
                // Write Label
                if (0 === $index) {
                    $fd = [];
                    foreach ($data as $key => $value) {
                        $fd[$resource[$key]['table']['label'] ?? $key] = $value;
                    }
                    $writer->write($fd);
                    continue;
                }

                // Data
                $writer->write($data);
            }

            $writer->close();
        }, 200, [
            'Content-Type' => $writer->getDefaultMimeType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                'export.'.$writer->getFormat()
            ),
        ]);
    }
}
