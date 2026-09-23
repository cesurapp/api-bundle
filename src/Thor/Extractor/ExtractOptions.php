<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Cesurapp\ApiBundle\Security\Attribute\IsGrantedAny;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

trait ExtractOptions
{
    public function extractOptions(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, array $thorAttr): array
    {
        $mainGroup = explode('/', ltrim($route->getPath(), '/'));
        $mainGroup = $mainGroup[1] ?? $mainGroup[0];

        return [
            'path' => $route->getPath(),
            'methods' => $route->getMethods() ?: ['GET'],
            'routeGroup' => '' !== $mainGroup ? $mainGroup : 'root',
            'shortName' => str_replace('Controller', '', $refController->getShortName()).ucfirst($refMethod->getShortName()),
            'shortController' => ucfirst(str_replace('Controller', '', $refController->getShortName())),
            'stack' => explode('|', $thorAttr['stack'] ?? '')[0],
            'stackOrder' => explode('|', $thorAttr['stack'] ?? '')[1] ?? null,
            'title' => $thorAttr['title'] ?? '',
            'info' => $thorAttr['info'] ?? '',
            'isHidden' => $thorAttr['isHidden'] ?? false,
            'isPaginate' => $thorAttr['isPaginate'] ?? false,
            'isAuth' => $thorAttr['isAuth'] ?? true,
            'order' => $thorAttr['order'] ?? 0,
            'roles' => $this->extractRoles($route, $refController, $refMethod, $thorAttr),
            'isFile' => $thorAttr['isFile'] ?? false,
        ];
    }

    private function extractRoles(Route $route, \ReflectionClass $class, \ReflectionMethod $method, array $thorAttr): array
    {
        $permissions = [];

        // Class and method level, read from the attribute objects: only the permission arguments,
        // never `message` / `subject` / `statusCode`.
        foreach ([$class, $method] as $reflection) {
            foreach ($reflection->getAttributes(IsGranted::class) as $attr) {
                $attribute = $attr->newInstance()->attribute;
                if (is_string($attribute)) {
                    $permissions[] = $attribute;
                }
            }

            foreach ($reflection->getAttributes(IsGrantedAny::class) as $attr) {
                array_push($permissions, ...array_filter($attr->newInstance()->attributes, 'is_string'));
            }
        }

        // Security Access Control Get Roles
        $accessControl = $this->bag->get('api.thor.access_control');
        foreach (is_array($accessControl) ? $accessControl : [] as $item) {
            if (!isset($item['path'], $item['roles'])) {
                continue;
            }

            if (preg_match('{'.str_replace('}', '\}', $item['path']).'}', $route->getPath())) {
                array_push($permissions, ...(array) $item['roles']);
            }
        }

        return array_values(array_unique(array_merge($permissions, $thorAttr['roles'] ?? [])));
    }
}
