<?php

namespace Cesurapp\ApiBundle\Thor\Extractor;

use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Cesurapp\ApiBundle\Exception\ValidationException;
use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Cesurapp\ApiBundle\Thor\Attribute\ThorResource;
use Symfony\Component\Routing\Route;

trait ExtractDto
{
    public function extractDto(\ReflectionClass $refController, \ReflectionMethod $refMethod, Route $route, array $thorAttr): array
    {
        return [
            'routeAttr' => $this->extractRouteAttr($route, $refMethod),
            'query' => $this->extractQueryParameters($thorAttr),
            'request' => $this->extractRequestParameters($thorAttr),
            'header' => $this->extractHeaderParameters($thorAttr),
            ...$this->extractResponse($thorAttr, $refMethod, $route->getMethods() ?: ['GET']),
        ];
    }

    /**
     * Extract Route Attributes.
     */
    private function extractRouteAttr(Route $route, \ReflectionMethod $method): array
    {
        $routerVars = $route->compile()->getVariables();
        if (!count($routerVars)) {
            return [];
        }

        $controllerArgs = array_values(
            array_filter($method->getParameters(), static function (\ReflectionParameter $p): bool {
                $check = static function (string $typeName): bool {
                    // Entity Object
                    if (strpos($typeName, 'Entity\\')) {
                        return true;
                    }

                    // Uuid Object
                    if (strpos($typeName, 'Uuid')) {
                        return true;
                    }

                    // Enum Object
                    if (enum_exists($typeName)) {
                        return true;
                    }

                    // Vendor Class
                    if (class_exists($typeName) || in_array($typeName, get_declared_interfaces(), true)) {
                        return false;
                    }

                    return true;
                };

                if ($p->getType() instanceof \ReflectionUnionType) {
                    return array_any(self::typeNames($p->getType()), $check);
                }

                // Disable Attributes
                if (count($p->getAttributes())) {
                    return false;
                }

                // Untyped: a plain route value
                $names = self::typeNames($p->getType());

                return [] === $names || $check($names[0]);
            })
        );

        $matched = [];
        if (count($routerVars) === count($controllerArgs)) {
            foreach ($routerVars as $index => $key) {
                $type = $controllerArgs[$index]->getType();
                $isNull = null !== $type && $type->allowsNull();

                // Remove Null
                $types = array_filter(self::typeNames($type), static fn (string $name) => 'null' !== $name) ?: ['string'];

                $matched[$key] = implode('|', array_unique(array_map(function ($type) use ($key, $isNull) {
                    if (class_exists($type)) {
                        $ref = new \ReflectionClass($type);
                        if ($ref->hasProperty($key)) {
                            return implode('|', $this->extractTypes($ref->getProperty($key)->getType(), $isNull));
                        }

                        if ($ref->isEnum()) {
                            $this->custom['_enums'][ThorExtractor::baseClass($type)] = $type;

                            return ($isNull ? '?' : '').$type;
                        }

                        return ($isNull ? '?' : '').'string';
                    }

                    return ($isNull ? '?' : '').$type;
                }, $types)));
            }
        }

        return $matched;
    }

    /**
     * Generate Get|Query Parameters.
     */
    private function extractQueryParameters(array $attrThor): array
    {
        $attr = [];

        // Append Paginator Query
        if (!empty($attrThor['isPaginate'])) {
            $attr['page'] = '?int';
            $attr['max'] = '?int';
        }

        // Append Doctrine Filter & Sort
        array_walk_recursive($attrThor['response'], function ($val) use (&$attr, $attrThor) {
            if (!is_array($val) && class_exists($val)) {
                $refClass = new \ReflectionClass($val);
                if ($refClass->implementsInterface(ApiResourceInterface::class)) {
                    $resource = $this->resourceLocator->getResource($val);

                    if (!empty($attrThor['isPaginate'])) {
                        // Sort
                        $sortableFields = array_filter($resource, static fn ($v) => !empty($v['table']['sortable']));
                        if (count($sortableFields)) {
                            $attr['sort'] = '?ASC|?DESC';
                            $attr['sort_by'] = implode('|', array_map(static fn ($v) => '?'.$v, array_keys($sortableFields)));
                        }

                        // Export
                        $exportFields = array_filter($resource, static fn ($v) => isset($v['table']));
                        if (count($exportFields)) {
                            $attr['export'] = '?csv|?xls';
                            $attr['export_field'] = '['.implode('|', array_map(static fn ($v) => '?'.$v, array_keys($exportFields))).']';
                        }

                        // Filter
                        $filteredFields = array_filter($resource, static fn ($v) => isset($v['filter']));
                        foreach ($filteredFields as $key => $value) {
                            if (!is_array($value['filter'])) {
                                $attr['filter'][$key] = '?any';
                            } else {
                                $attr['filter'][$key] = array_map(static fn ($v) => '?any', array_flip(array_keys($value['filter'])));
                            }
                        }
                    }
                }
            }
        });

        return array_replace_recursive($attr, $attrThor['query'] ?? []);
    }

    /**
     * Generate Header Parameters.
     */
    private function extractHeaderParameters(array $attrThor): array
    {
        $attr = [];

        // Append Auth Header
        if (!empty($attrThor['isAuth'])) {
            $attr = $attrThor['authHeader'] ?? [];
        }

        return array_replace_recursive($attr, $attrThor['header'] ?? []);
    }

    /**
     * Generate Post|DTO Parameters.
     */
    private function extractRequestParameters(array $attrThor): array
    {
        $attr = [];

        // Extract DTO Parameters
        if (isset($attrThor['dto'])) {
            $dto = new \ReflectionClass($attrThor['dto']);
            if ($dto->isSubclassOf(ApiDto::class)) {
                $attr = array_replace_recursive($attr, $this->extractDTOClass($dto));
            }
        }

        return array_replace_recursive($attr, $attrThor['request'] ?? []);
    }

    /**
     * Extract Request Validation Parameters using AbstractApiDto.
     */
    private function extractDTOClass(\ReflectionClass $class): array
    {
        $parameters = [];

        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $values = [];

            $extractedTypes = $this->extractTypes($property->getType());
            foreach ($extractedTypes as $t) {
                if (enum_exists($t)) {
                    $this->custom['_enums'][ThorExtractor::baseClass($t)] = $t;
                }
            }

            // Extract Types
            $types = implode('|', $extractedTypes);
            if ($types) {
                $values['types'] = $types;
            }

            // Api Resource
            $apiResource = $property->getAttributes(ThorResource::class);
            if (count($apiResource)) {
                $r = $apiResource[0]->getArguments();

                if (isset($r['callback'])) {
                    $data = call_user_func($r['callback']);
                    $parameters[$property->getName()] = $r['callbackMultiple'] ? ['mArray' => $data] : implode('|', array_map(static fn ($v) => '?'.$v, $data));
                } else {
                    if (!array_is_list($r['data'])) {
                        $parameters[$property->getName()] = $r['data'];
                    } else {
                        $parameters[$property->getName()] = $this->isNestedArray($r['data']) ? $r['data'] : implode('|', array_map(static fn ($v) => '?'.$v, $r['data']));
                    }
                }

                continue;
            }

            // Validation
            $valids = $this->renderValidationAttributes($property->getAttributes());
            if ($valids['validations']) {
                $values['validations'] = $valids['validations'];
            }

            $parameters[$property->getName()] = implode(';', $values);
        }

        return $parameters;
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     */
    private function renderValidationAttributes(array $attributes): array
    {
        $validations = implode('|', array_map(function ($attribute) {
            $args = $attribute->getArguments() ? '('.http_build_query($attribute->getArguments(), '', ', ').')' : '';

            return ThorExtractor::baseClass($attribute->getName()).$args;
        }, $attributes));

        return [
            'validations' => $validations,
            'items' => [],
        ];
    }

    /**
     * Generate Exceptions.
     */
    private function extractResponse(array $thorAttr, \ReflectionMethod $refMethod, array $methods): array
    {

        $thorAttr['exception'] = [];

        array_walk_recursive($thorAttr['response'], function (&$resValue) use (&$thorAttr) {
            // Class
            if (is_string($resValue) && class_exists($resValue)) {
                $class = $resValue;
                $refClass = new \ReflectionClass($class);

                // Resources && DataTable
                if ($refClass->implementsInterface(ApiResourceInterface::class)) {
                    $resource = $this->resourceLocator->getResource($class);
                    $resValue = !empty($thorAttr['isPaginate']) ? [$resValue] : $resValue;

                    if (!empty($thorAttr['isPaginate'])) {
                        $tableFields = array_filter($resource, static fn ($v) => isset($v['table']));
                        if (count($tableFields)) {
                            foreach ($tableFields as $key => $tableField) {
                                if (isset($tableField['table']['sortable_field'])) {
                                    unset($tableFields[$key]['table']['sortable_field']);
                                }
                                if (isset($tableField['table']['exporter'])) {
                                    unset($tableFields[$key]['table']['exporter']);
                                }
                                if (isset($tableField['table'])) {
                                    $tableFields[$key]['table']['export'] = true;
                                }
                            }
                            $thorAttr['table'] = $tableFields;
                        }
                    }
                }

                // Exceptions
                if ($refClass->implementsInterface(\Throwable::class)) {
                    $exception = $this->renderException($class);
                    $thorAttr['exception'][$refClass->getShortName()] = $exception;
                    $resValue = null;
                }
            }
        });

        // Clear Null Response
        foreach ($thorAttr['response'] as $key => $res) {
            if (!$res) {
                unset($thorAttr['response'][$key]);
            }
        }

        // Append Message Format: the types of the addMessage() calls; without a type it is SUCCESS
        $source = $this->getMethodSource($refMethod);
        if (preg_match_all('/->addMessage\(((?:[^()]|\((?1)\))*)\)/', $source, $calls)) {
            $content = ['message' => []];

            foreach ($calls[1] as $arguments) {
                $type = preg_match('/MessageType::(SUCCESS|ERROR|WARNING|INFO)\b/', $arguments, $m) ? strtolower($m[1]) : 'success';
                $content['message'][$type] = '?array';
            }

            $thorAttr['response'][200] = array_merge($thorAttr['response'][200] ?? [], $content);
        }

        // Append DTO Exception Response
        if (isset($thorAttr['dto']) && !in_array('GET', $methods, false)) {
            $exception = $this->renderException(ValidationException::class);
            $thorAttr['exception'][$exception['code']] = $exception;
        }

        // Append Pagination
        if (!empty($thorAttr['isPaginate'])) {
            $thorAttr['response'][200]['pager'] = [
                'max' => 'int',
                'current' => 'int',
                'prev' => '?int',
                'next' => '?int',
                'total' => '?int',
            ];
        }

        ksort($thorAttr['response']);

        return [
            'response' => $thorAttr['response'],
            'exception' => $thorAttr['exception'],
            'table' => $thorAttr['table'] ?? null,
        ];
    }

    /**
     * Documented shape of an exception response.
     *
     * @param class-string $class
     */
    private function renderException(string $class): array
    {
        $refClass = new \ReflectionClass($class);
        $parameters = [];
        foreach ($refClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }

        $default = static fn (string $name, mixed $fallback) => isset($parameters[$name]) && $parameters[$name]->isDefaultValueAvailable()
            ? $parameters[$name]->getDefaultValue()
            : $fallback;
        $exceptionCode = $default('code', 400);
        $message = $default('message', '');

        // Create Class (an exception with required constructor arguments keeps the defaults read above)
        try {
            $eClass = $refClass->newInstance();

            if ($eClass instanceof \Throwable) {
                $message = $eClass->getMessage();
                if ($eClass->getCode()) {
                    $exceptionCode = $eClass->getCode();
                }
            }
            if (method_exists($eClass, 'getMessageKey')) {
                $message = $eClass->getMessageKey();
            }
            if (method_exists($eClass, 'getStatusCode')) {
                $exceptionCode = $eClass->getStatusCode();
            }
        } catch (\Throwable) {
        }

        $exception = [
            'type' => $refClass->getShortName(),
            'code' => $exceptionCode < 1 ? 400 : $exceptionCode,
            'message' => $message,
        ];

        if (isset($parameters['errors'])) {
            $exception['errors'] = [];
        }

        return $exception;
    }

    /**
     * ReflectionMethod Get Source Code.
     */
    private function getMethodSource(\ReflectionMethod $method): string
    {
        $file = (string) $method->getFileName();
        // One read per controller file, not one per route
        $lines = $this->sourceCache[$file] ??= (file($file) ?: []);

        $start = $method->getStartLine() - 1;

        return trim(implode('', array_slice($lines, $start, $method->getEndLine() - $start)));
    }

    private function extractTypes(?\ReflectionType $type, bool $isNull = false): array
    {
        $types = [];

        if ($type instanceof \ReflectionUnionType) {
            $isNull = !$isNull ? $type->allowsNull() : true;

            foreach (self::typeNames($type) as $name) {
                if (class_exists($name)) {
                    $types[] = $isNull ? '?string' : 'string';
                    $types[] = $isNull ? '?int' : 'int';
                } elseif ('null' !== $name) {
                    $types[] = ($isNull ? '?' : '').$name;
                }
            }
        } elseif (null !== $type) {
            $types[] = ($type->allowsNull() ? '?' : '').(self::typeNames($type)[0] ?? 'mixed');
        }

        return array_unique($types);
    }

    /**
     * @return list<string> names of a type; an intersection (or DNF part) counts as "object"
     */
    private static function typeNames(?\ReflectionType $type): array
    {
        return match (true) {
            $type instanceof \ReflectionNamedType => [$type->getName()],
            $type instanceof \ReflectionUnionType => array_values(array_map(static fn (\ReflectionType $t) => $t instanceof \ReflectionNamedType ? $t->getName() : 'object', $type->getTypes())),
            $type instanceof \ReflectionIntersectionType => ['object'],
            default => [],
        };
    }

    private function isNestedArray(array $array): bool
    {
        return array_any($array, fn ($value) => is_array($value));
    }
}
