<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Cesurapp\ApiBundle\Response\ApiResourceLocator;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Cesurapp\ApiBundle\Thor\Event\ThorDataEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class ThorExtractor
{
    use ExtractOptions;
    use ExtractController;
    use ExtractDto;

    public array $custom = [];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ParameterBagInterface $bag,
        private readonly ApiResourceLocator $resourceLocator,
        private readonly EventDispatcherInterface $dispatcher
    ) {
        $this->custom = [
            '_enums' => [],
        ];
    }

    /**
     * Render Documentation Template.
     */
    public function render(?array $data = null): string
    {
        // Template Data
        if (!$data) {
            $data = $this->extractData(true);
        }

        // Resource Extractor
        array_walk_recursive($data, static function (&$val) use ($data) {
            if (is_string($val) && class_exists($val) && in_array(ApiResourceInterface::class, class_implements($val), true)) {
                $val = $data['_resource'][ThorExtractor::baseClass($val) .':'. ThorExtractor::basePath($val)];
            }
        });

        // Global Variable
        $statusText = Response::$statusTexts;

        // Render Response
        ob_start();
        include __DIR__.'/../Template/base.html.php';

        return ob_get_clean();
    }

    /**
     * Extract Data.
     */
    public function extractData(bool $grouped = false): array
    {
        $data = [];

        foreach ($this->routerList() as $path => $route) {
            $refController = new \ReflectionClass($route['controller']);
            $refMethod = $refController->getMethod($route['method']);
            $routeId = explode('::', $path)[0];

            // Find Thor Attribute
            $attrThor = $refMethod->getAttributes(Thor::class);
            $attrThor = isset($attrThor[0]) ? $attrThor[0]->getArguments() : [];
            $attrThor = array_replace_recursive($this->bag->get('api.thor.global_config'), $attrThor);
            if (!empty($attrThor['isHidden'])) {
                continue;
            }

            $data[$routeId] = [
                ...$this->extractOptions($refController, $refMethod, $route['router'], $attrThor),
                ...$this->extractController($refController, $refMethod, $route['router'], $this->bag->get('kernel.project_dir')),
                ...$this->extractDto($refController, $refMethod, $route['router'], $attrThor),
            ];
        }

        // Sort Data
        sort($data);

        if ($grouped) {
            $newDoc = [];
            foreach ($data as $index => $doc) {
                if ($doc['stack']) {
                    $newDoc[$doc['stack']][] = $doc;
                    continue;
                }

                $first = explode('/', $doc['path'])[1];
                $newDoc[ucfirst($first)][] = $doc;
            }

            $findOrder = static function ($data) {
                foreach ($data as $item) {
                    $order = $item['stackOrder'] ?? null;
                    if (null !== $order) {
                        return (int) $order;
                    }
                }

                return 20000;
            };

            // Sort Group
            uasort($newDoc, static function ($a, $b) use ($findOrder) {
                if (($ao = $findOrder($a)) === ($bo = $findOrder($b))) {
                    return 0;
                }

                return $ao < $bo ? -1 : 1;
            });

            // Sort Items
            foreach ($newDoc as $key => $items) {
                uasort($items, static function ($a, $b) {
                    if ($a['order'] === $b['order']) {
                        return 0;
                    }

                    return $a['order'] < $b['order'] ? -1 : 1;
                });

                $newDoc[$key] = $items;
            }

            $data = $newDoc;
        }

        $data['_resource'] = $this->extractResources();
        $data['_enums'] = $this->custom['_enums'];
        $this->dispatcher->dispatch(new ThorDataEvent($data));

        return $data;
    }

    private function extractResources(): array
    {
        $resources = [];

        foreach ($this->resourceLocator->all() as $class => $type) {
            $resource = $this->resourceLocator->get($class)->toResource();
            $newRes = [];
            foreach ($resource as $key => $data) {
                if (isset($data['type'])) {
                    $newRes[$key] = $data['type'];
                }
            }
            $resources[ThorExtractor::baseClass($class) .':'. ThorExtractor::basePath($class)] = $newRes;
        }

        return $resources;
    }

    private function routerList(): array
    {
        $list = [];

        foreach ($this->router->getRouteCollection()->all() as $index => $router) {
            if ($router->getDefault('_controller')) {
                [$controller, $method] = explode('::', $router->getDefault('_controller'));
                if (!class_exists($controller)) {
                    continue;
                }

                $list[$index.'::'.$router->getPath()] = [
                    'controller' => $controller,
                    'method' => $method,
                    'router' => $router,
                ];
            }
        }

        return $list;
    }

    public static function baseClass(string|object|null $class): ?string
    {
        return $class ? basename(str_replace('\\', '/', is_object($class) ? get_class($class) : $class)) : null;
    }

    public static function basePath(string|null $class): ?string
    {
        if (! $class) {
            return null;
        }

        $mainGroup = explode('\\', preg_replace('/App\\\/', '', $class));
        $mainGroup = strtolower(str_replace('_', '', $mainGroup[0] ?? ''));

        return $mainGroup;
    }
}
