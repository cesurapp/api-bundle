<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

trait ExtractOptions
{
    public function extractOptions(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, array $thorAttr): array
    {
        $mainGroup = explode('/', ltrim($route->getPath(), '/'));
        $mainGroup = $mainGroup[1] ?? $mainGroup[0] ?? '';

        return [
            'path' => $route->getPath(),
            'methods' => $route->getMethods() ?: ['GET'],
            'routeGroup' => $mainGroup,
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
            'roles' => $this->extractRoles($route, $refMethod, $thorAttr),
            'isFile' => $thorAttr['isFile'] ?? false,
        ];
    }

    private function extractRoles(Route $route, \ReflectionMethod $method, array $thorAttr): array
    {
        $permissions = $method->getAttributes(IsGranted::class);
        if ($permissions) {
            $permissions = $permissions[0]->getArguments();
        }

        // Security Access Control Get Roles
        $accessControl = $this->bag->get('api.thor.access_control');
        if ($accessControl) {
            foreach ($accessControl as $item) {
                if (preg_match("{{$item['path']}}", $route->getPath())) {
                    $arr = !is_array($item['roles']) ? [$item['roles']] : $item['roles'];
                    array_push($permissions, ...$arr);
                }
            }
        }

        return array_unique(array_merge($permissions, $thorAttr['roles'] ?? []));
    }
}
