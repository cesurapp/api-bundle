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

    /** @var array<string, list<string>> controller file => lines */
    private array $sourceCache = [];

    /** @var array<string, bool> */
    private static array $resourceClassCache = [];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ParameterBagInterface $bag,
        private readonly ApiResourceLocator $resourceLocator,
        private readonly EventDispatcherInterface $dispatcher,
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
            if (is_string($val) && ThorExtractor::isResourceClass($val)) {
                $val = $data['_resource'][ThorExtractor::baseClass($val).':'.ThorExtractor::basePath($val)];
            }
        });

        // Global Variable
        $statusText = Response::$statusTexts;

        // Render Response
        ob_start();
        include __DIR__.'/../Template/base.html.php';

        $output = ob_get_clean();
        if (false === $output) {
            throw new \LogicException('The template closed the output buffer.');
        }

        return $output;
    }

    /**
     * Extract Data.
     */
    public function extractData(bool $grouped = false): array
    {
        $data = [];
        $this->custom['_enums'] = [];
        $withSource = 'dev' === $this->bag->get('kernel.environment');
        $globalConfig = $this->bag->get('api.thor.global_config');
        $projectDir = $this->bag->get('kernel.project_dir');

        foreach ($this->routerList() as $path => $route) {
            $refController = new \ReflectionClass($route['controller']);
            $refMethod = $refController->getMethod($route['method']);
            $routeId = explode('::', $path)[0];

            // Find Thor Attribute
            $attrThor = $refMethod->getAttributes(Thor::class);
            $attrThor = isset($attrThor[0]) ? $attrThor[0]->getArguments() : [];
            $attrThor = array_replace_recursive(is_array($globalConfig) ? $globalConfig : [], $attrThor);
            if (!empty($attrThor['isHidden'])) {
                continue;
            }

            $data[$routeId] = [
                ...$this->extractOptions($refController, $refMethod, $route['router'], $attrThor),
                ...$this->extractController($refController, $refMethod, $route['router'], is_string($projectDir) ? $projectDir : '', $withSource),
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
            $resources[ThorExtractor::baseClass($class).':'.ThorExtractor::basePath($class)] = $newRes;
        }

        return $resources;
    }

    private function routerList(): array
    {
        $list = [];

        foreach ($this->router->getRouteCollection()->all() as $index => $router) {
            $target = $router->getDefault('_controller');
            if ($target) {
                // "Class::method", [Class, method] or an invokable "Class"
                [$controller, $method] = is_array($target) ? array_values($target) + [1 => '__invoke'] : explode('::', (string) $target, 2) + [1 => '__invoke'];
                if (!is_string($controller) || !class_exists($controller) || !method_exists($controller, (string) $method)) {
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

    /**
     * Memoized: the extractor and the TS generator ask this for every string leaf of the docs.
     */
    public static function isResourceClass(string $class): bool
    {
        return self::$resourceClassCache[$class] ??= class_exists($class) && is_subclass_of($class, ApiResourceInterface::class);
    }

    public static function baseClass(string|object|null $class): ?string
    {
        return $class ? basename(str_replace('\\', '/', is_object($class) ? get_class($class) : $class)) : null;
    }

    public static function basePath(?string $class): ?string
    {
        if (! $class) {
            return null;
        }

        $mainGroup = explode('\\', preg_replace('/App\\\/', '', $class) ?? $class);
        $mainGroup = strtolower(str_replace('_', '', $mainGroup[0]));

        return $mainGroup;
    }
}
