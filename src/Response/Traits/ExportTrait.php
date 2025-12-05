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

    private function isExport(Request $request, array $resource): bool
    {
        return $this->getAll($request, 'export') && array_filter($resource, static fn ($v) => isset($v['table']));
    }

    /**
     * Export to XLS | Csv.
     */
    private function exportStream(QueryBuilder|Query $builder, Request $request, array $resource): StreamedResponse
    {
        $resource = array_filter($resource, static fn ($v) => isset($v['table']));
        $exportFields = $this->getAll($request, 'export_field') ?? [];
        $fields = array_intersect(array_map('strtolower', $exportFields), array_keys($resource)) ?: array_keys($resource);

        // Source
        $source = new DoctrineORMQuerySourceIterator(
            $builder->getQuery(),
            $fields,
            array_map(static fn ($v) => $v['table'] ?? [], $resource)
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
