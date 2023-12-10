<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Symfony\Component\Routing\Route;

trait ExtractController
{
    public function extractController(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, string $projectDir): array
    {
        return [
            'controller' => $route->getDefault('_controller'),
            'controllerPath' => str_replace($projectDir, '', $refController->getFileName()),
            'controllerLine' => $refMethod->getStartLine(),
            'controllerResponseType' => $this->getResponseType($refMethod->getReturnType()),
        ];
    }

    private function getResponseType(\ReflectionNamedType|\ReflectionUnionType|\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionUnionType) {
            return implode('|', array_map(static fn (\ReflectionNamedType $t) => ThorExtractor::baseClass($t->getName()), $type->getTypes()));
        }

        if ($type instanceof \ReflectionNamedType) {
            return ThorExtractor::baseClass($type->getName());
        }

        return 'Mixed';
    }
}
