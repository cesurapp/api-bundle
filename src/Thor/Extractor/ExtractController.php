<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Symfony\Component\Routing\Route;

trait ExtractController
{
    /**
     * Controller class, file and line are only for the dev "open in PhpStorm" link: the docs are
     * public in other environments and must not map the application's source tree.
     */
    public function extractController(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, string $projectDir, bool $withSource = false): array
    {
        $data = [
            'controllerResponseType' => $this->getResponseType($refMethod->getReturnType()),
        ];

        if ($withSource) {
            $data['controller'] = $route->getDefault('_controller');
            $data['controllerPath'] = str_replace($projectDir, '', (string) $refController->getFileName());
            $data['controllerLine'] = $refMethod->getStartLine();
        }

        return $data;
    }

    private function getResponseType(?\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(static fn (\ReflectionType $t) => $t instanceof \ReflectionNamedType ? ThorExtractor::baseClass($t->getName()) : 'Mixed', $type->getTypes()));
        }

        if ($type instanceof \ReflectionNamedType) {
            return (string) ThorExtractor::baseClass($type->getName());
        }

        return 'Mixed';
    }
}
