<?php

namespace Cesurapp\ApiBundle\Thor\Generator;

use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

class TypeScriptGenerator
{
    private string $path;
    private TypeScriptHelper $helper;
    private array $routeGroups = [];

    public function __construct(private readonly array $data)
    {
        $this->routeGroups = array_map(
            fn($arr) => array_column($arr, 'routeGroup'),
            array_filter($this->data, static fn ($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY)
        );
        $this->routeGroups = array_unique(array_merge(...array_values($this->routeGroups)));

        // Create Template Helper
        $this->helper = new TypeScriptHelper();

        // Generate Path
        $this->path = sys_get_temp_dir().uniqid('', false);
        foreach ($this->routeGroups as $route) {
            foreach (['response', 'request', 'query', 'table', 'resource'] as $dir) {
                $p = sprintf('%s/%s/%s', $this->path, $route, $dir);
                if (!mkdir($p, 0777, true) && !is_dir($p)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $p));
                }
            }
        }

        foreach (['enum'] as $dir) {
            $p = sprintf('%s/%s', $this->path, $dir);
            if (!mkdir($p, 0777, true) && !is_dir($p)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $p));
            }
        }
    }

    /**
     * Generate TS Files.
     */
    public function generate(): self
    {
        foreach ($this->data as $key => $groupRoutes) {
            if (str_starts_with($key, '_')) {
                switch ($key) {
                    case '_enums': $this->generateEnum($groupRoutes);
                        break;
                    case '_resource': $this->generateResources($groupRoutes);
                }

                continue;
            }

            foreach ($groupRoutes as $route) {
                $this->generateResponse($route);
                $this->generateRequest($route);
                $this->generateQuery($route);
                $this->generateTable($route);
            }
        }

        // Render Route Groups
        foreach ($this->routeGroups as $routeGroup) {
            file_put_contents($this->path."/$routeGroup.ts", $this->renderTemplate('routeGroup.ts.php', [
                'data' => array_map(function ($data) use ($routeGroup) {
                    return array_filter($data, fn($item) => $item['routeGroup'] === $routeGroup);
                }, array_filter($this->data, static fn ($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY)),
                'routeGroup' => $routeGroup
            ]));
        }
        // Render Index
        file_put_contents($this->path.'/index.ts', $this->renderTemplate('index.ts.php', [
            'routeGroups' => $this->routeGroups,
        ]));

        // Render Dependency
        file_put_contents($this->path.'/flatten.ts', $this->renderTemplate('flatten.ts.php', [
            'data' => $this->data,
        ]));
        file_put_contents($this->path.'/tsconfig.json', $this->renderTemplate('tsconfig.json.php', []));

        return $this;
    }

    /**
     * Compress TS Library to TAR Format.
     */
    public function compress(?string $path = null): File
    {
        $tmpPath = $path ?? sys_get_temp_dir();

        shell_exec("tar -czvf $tmpPath/Api.tar.bz2 -C $this->path . 2>&1");
        while (true) {
            if (file_exists($tmpPath.'/Api.tar.bz2')) {
                break;
            }
            usleep(50000);
        }

        return new File($tmpPath.'/Api.tar.bz2');
    }

    /**
     * Copy Generated Directory to Custom Path.
     */
    public function copyFiles(string $path): void
    {
        $fs = new Filesystem();

        // Remove Old Path
        if (file_exists($path)) {
            $fs->remove($path);
        }

        $fs->mirror($this->path, $path);
    }

    /**
     * Render PHP Template.
     */
    private function renderTemplate(string $template, array $data = []): string
    {
        $data['helper'] = $this->helper;
        extract($data, EXTR_OVERWRITE);

        ob_start();
        include __DIR__.'/../Template/typescript/'.$template;

        return ob_get_clean();
    }

    /**
     * Generate Response Parameters.
     */
    private function generateResponse(array $route): void
    {
        if (!$route['response']) {
            return;
        }

        $resources = [];
        array_walk_recursive($route, static function ($val) use (&$resources, $route) {
            if (is_string($val) && class_exists($val) && in_array(ApiResourceInterface::class, class_implements($val), true)) {
                $resources[] = sprintf('%s/resource/%s', ThorExtractor::basePath($val), ThorExtractor::baseClass($val));
            }
        });

        $name = sprintf('%sResponse.ts', ucfirst($route['shortName']));
        file_put_contents(sprintf('%s/%s/response/%s', $this->path,$route['routeGroup'],$name), $this->renderTemplate('response.ts.php', [
            'data' => $route,
            'resources' => $resources,
        ]));
    }

    /**
     * Generate POST|PUT|PATCH Parameters.
     */
    private function generateRequest(array $route): void
    {
        if (!$route['request']) {
            return;
        }

        $name = sprintf('%sRequest.ts', ucfirst($route['shortName']));
        file_put_contents(sprintf('%s/%s/request/%s', $this->path,$route['routeGroup'],$name), $this->renderTemplate('request.ts.php', [
            'data' => $route,
        ]));
    }

    /**
     * Generate GET Parameters.
     */
    private function generateQuery(array $route): void
    {
        if (!$route['query']) {
            return;
        }

        $name = sprintf('%sQuery.ts', ucfirst($route['shortName']));
        file_put_contents(sprintf('%s/%s/query/%s', $this->path,$route['routeGroup'],$name), $this->renderTemplate('query.ts.php', [
            'data' => $route,
        ]));
    }

    /**
     * Generate DataTable Columns.
     */
    private function generateTable(array $route): void
    {
        if (!$route['table']) {
            return;
        }

        $name = sprintf('%sTable.ts', ucfirst($route['shortName']));
        file_put_contents(sprintf('%s/%s/table/%s', $this->path,$route['routeGroup'],$name), $this->renderTemplate('table.ts.php', [
            'data' => $route,
        ]));
    }

    /**
     * Generate DataTable Columns.
     */
    private function generateEnum(array $enumsGroup): void
    {
        foreach ($enumsGroup as $namespace => $enumData) {
            $file = 'Permission' === $namespace ? 'permission.ts.php' : 'enum.ts.php';
            $name = sprintf('%s.ts', ucfirst($namespace));
            $enumData = is_array($enumData) ? $enumData : $enumData::cases();
            file_put_contents(sprintf('%s/enum/%s', $this->path,$name), $this->renderTemplate($file, [
                'namespace' => $namespace,
                'data' => $enumData,
            ]));
        }
    }

    /**
     * Generate Resources.
     */
    private function generateResources(array $resourceGroup): void
    {
        foreach ($resourceGroup as $namespace => $data) {
            $resources = [];
            array_walk_recursive($data, static function ($val) use (&$resources) {
                if (is_string($val) && class_exists($val) && in_array(ApiResourceInterface::class, class_implements($val), true)) {
                    $resources[] = sprintf('%s/resource/%s', ThorExtractor::basePath($val), ThorExtractor::baseClass($val));
                }
            });

            foreach ($data as $index => $val) {
                if (is_array($val)) {
                    $data[$index] = [...$val, '[key:string]' => 'any'];
                }
            }

            [$namespace, $routeGroup] = explode(':', $namespace);
            $name = sprintf('%s.ts', ucfirst($namespace));
            file_put_contents(sprintf('%s/%s/resource/%s', $this->path,$routeGroup,$name), $this->renderTemplate('resource.ts.php', [
                'namespace' => $namespace,
                'data' => $data,
                'resources' => $resources,
            ]));
        }
    }
}
