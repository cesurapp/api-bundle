<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Cesurapp\ApiBundle\Security\Attribute\IsGrantedAny;

trait ExtractOptions
{
    public function extractOptions(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, array $thorAttr): array
    {
        $mainGroup = explode('/', ltrim($route->getPath(), '/'));
        $mainGroup = $mainGroup[1] ?? $mainGroup[0];

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
        $permissions = [];

        // Symfony's built-in IsGranted (single attribute)
        $isGrantedAttrs = $method->getAttributes(IsGranted::class);
        if ($isGrantedAttrs) {
            // getArguments returns a numerically indexed array where the first is the attribute
            $args = $isGrantedAttrs[0]->getArguments();
            if ($args) {
                $first = $args[0] ?? null;
                if (is_string($first)) {
                    $permissions[] = $first;
                } elseif (is_array($first)) {
                    $permissions = array_merge($permissions, array_values(array_filter($first, 'is_string')));
                }
            }
        }

        // Custom IsGrantedAny (multiple attributes)
        $anyAttrs = $method->getAttributes(IsGrantedAny::class);
        foreach ($anyAttrs as $attr) {
            $args = $attr->getArguments();
            foreach ($args as $arg) {
                if (is_string($arg)) {
                    $permissions[] = $arg;
                } elseif (is_array($arg)) {
                    $permissions = array_merge($permissions, array_values(array_filter($arg, 'is_string')));
                }
            }
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
