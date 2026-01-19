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

        /** @var \ReflectionParameter[] $controllerArgs */
        $controllerArgs = array_values(
            array_filter($method->getParameters(), static function ($p) {
                $check = static function ($typeName) {
                    // Entity Object
                    if (strpos($typeName, 'Entity\\')) {
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
                    return count(array_filter($p->getType()->getTypes(), static fn ($item) => $check($item->getName())));
                }

                // Disable Attributes
                if (count($p->getAttributes())) {
                    return false;
                }

                return $check($p->getType()->getName()); // @phpstan-ignore-line
            })
        );

        $matched = [];
        if (count($routerVars) === count($controllerArgs)) {
            foreach ($routerVars as $index => $key) {
                $isNull = false;

                if ($controllerArgs[$index]->getType() instanceof \ReflectionUnionType) {
                    if ($controllerArgs[$index]->getType()->allowsNull()) {
                        $isNull = true;
                    }
                    $types = array_map(static fn ($p) => $p->getName(), $controllerArgs[$index]->getType()->getTypes());
                } else {
                    if ($controllerArgs[$index]->getType()->allowsNull()) {
                        $isNull = true;
                    }
                    $types = [$controllerArgs[$index]->getType()->getName()]; // @phpstan-ignore-line
                }

                // Remove Null
                if (in_array('null', $types, true)) {
                    unset($types[array_search('null', $types, true)]);
                }

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

                        return ($isNull ? '?' : '').'mixed';
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
        // Render Exception Class
        $renderException = static function (\ReflectionClass|string $refClass, int|string $code) {
            if (is_string($refClass)) {
                $refClass = new \ReflectionClass($refClass);
            }
            $parameters = array_reduce($refClass->getConstructor()?->getParameters(), static function ($result, $item) {
                $result[$item->name] = $item;

                return $result;
            }, []);

            $exceptionCode = isset($parameters['code']) ? $parameters['code']->getDefaultValue() : 400;
            $message = $parameters['message']->getDefaultValue();

            // Create Class
            try {
                $eClass = new ($refClass->getName())();

                if ($refClass->hasMethod('getMessage')) {
                    $message = $eClass->getMessage();
                }
                if ($refClass->hasMethod('getMessageKey')) {
                    $message = $eClass->getMessageKey();
                }
                if ($eClass->getCode()) {
                    $exceptionCode = $eClass->getCode();
                }
                if ($refClass->hasMethod('getStatusCode')) {
                    $exceptionCode = $eClass->getStatusCode();
                }
            } catch (\Exception $exception) {
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
        };

        $thorAttr['exception'] = [];

        array_walk_recursive($thorAttr['response'], function (&$resValue, $resKey) use ($renderException, &$thorAttr) {
            // Class
            if (!is_array($resValue) && class_exists($resValue)) {
                $refClass = new \ReflectionClass($resValue);

                // Resources && DataTable
                if ($refClass->implementsInterface(ApiResourceInterface::class)) {
                    $resource = $this->resourceLocator->getResource($resValue);
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
                    $exception = $renderException($resValue, $resKey);
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

        // Append Message Format
        $source = $this->getMethodSource($refMethod);
        if (str_contains($source, '->addMessage(')) {
            $content = ['message' => []];

            if (str_contains($source, 'MessageType::ERROR')) {
                $content['message']['error'] = '?array';
            }
            if (str_contains($source, 'MessageType::WARNING')) {
                $content['message']['warning'] = '?array';
            }
            if (str_contains($source, 'MessageType::INFO')) {
                $content['message']['info'] = '?array';
            }
            if (str_contains($source, 'MessageType::SUCCESS') || false !== preg_match('/addMessage[^\:\:]+$/', $source)) {
                $content['message']['success'] = '?array';
            }

            $thorAttr['response'][200] = array_merge($thorAttr['response'][200] ?? [], $content);
        }

        // Append DTO Exception Response
        if (isset($thorAttr['dto']) && !in_array('GET', $methods, false)) {
            $exception = $renderException(ValidationException::class, 403);
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
     * ReflectionMethod Get Source Code.
     */
    private function getMethodSource(\ReflectionMethod $method): string
    {
        $start_line = $method->getStartLine() - 1;
        $length = $method->getEndLine() - $start_line;
        $source = file($method->getFileName());

        return trim(implode('', array_slice($source, $start_line, $length)));
    }

    private function extractTypes(\ReflectionType|\ReflectionNamedType $type, bool $isNull = false): array
    {
        $types = [];

        if ($type instanceof \ReflectionUnionType) {
            $isNull = !$isNull ? $type->allowsNull() : true;

            foreach ($type->getTypes() as $item) {
                if (class_exists($item->getName())) {
                    $types[] = $isNull ? '?string' : 'string';
                    $types[] = $isNull ? '?int' : 'int';
                } elseif ('null' !== $item->getName()) {
                    $types[] = ($isNull ? '?' : '').$item->getName();
                }
            }
        } else {
            $types[] = ($type->allowsNull() ? '?' : '').$type->getName(); // @phpstan-ignore-line
        }

        return array_unique($types);
    }

    private function isNestedArray(array $array): bool
    {
        return array_any($array, fn ($value) => is_array($value));
    }
}
