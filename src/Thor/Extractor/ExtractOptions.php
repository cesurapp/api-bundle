<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

trait ExtractOptions
{
    public function extractOptions(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, array $thorAttr): array
    {
        return [
            'path' => $route->getPath(),
            'methods' => $route->getMethods() ?: ['GET'],
            'shortName' => lcfirst(str_replace('Controller', '', $refController->getShortName())).ucfirst($refMethod->getShortName()),
            'shortController' => ucfirst(str_replace('Controller', '', $refController->getShortName())),
            'stack' => explode('|', $thorAttr['stack'] ?? '')[0],
            'stackOrder' => explode('|', $thorAttr['stack'] ?? '')[1] ?? null,
            'title' => $thorAttr['title'] ?? '',
            'info' => $thorAttr['info'] ?? '',
            'isHidden' => $thorAttr['isHidden'] ?? false,
            'isPaginate' => $thorAttr['isPaginate'] ?? false,
            'isAuth' => $thorAttr['isAuth'] ?? true,
            'order' => $thorAttr['order'] ?? 0,
            'roles' => $this->extractRoles($refMethod, $thorAttr),
        ];
    }

    private function extractRoles(\ReflectionMethod $method, array $thorAttr): array
    {
        $permission = $method->getAttributes(IsGranted::class);
        if ($permission) {
            $permission = $permission[0]->getArguments();
        }

        return array_merge($permission, $thorAttr['roles'] ?? []);
    }
}
