# Symfony Api Bundle

[![App Tester](https://github.com/cesurapp/api-bundle/actions/workflows/testing.yaml/badge.svg)](https://github.com/cesurapp/api-bundle/actions/workflows/testing.yaml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?logo=Unlicense)](LICENSE.md)

This package allows you to expose fast api endpoints with Symfony. 

__Features:__
* Json request body transformer 
* Error messages are collected under a single format.
* Language translation is applied to all error messages.
* Custom cors header support
* Automatic documentation generator (Thor)
* Typescript client generator
* Api DTO resolver
* Doctrine filter & sorter resource
* PhoneNumber, UniqueEntity, Username validator
* Excel, Csv exporter (Sonata Export Bundle)

### Install
Required Symfony 7
```shell
composer req cesurapp/api-bundle
```

__Configuration:__ config/packages/api.yaml
```yaml
api:
  exception_converter: false
  cors_header:
    - { name: 'Access-Control-Allow-Origin', value: '*' }
    - { name: 'Access-Control-Allow-Methods', value: 'GET,POST,PUT,PATCH,DELETE' }
    - { name: 'Access-Control-Allow-Headers', value: '*' }
    - { name: 'Access-Control-Expose-Headers', value: 'Content-Disposition' }
  thor:
    base_url: "%env(APP_DEFAULT_URI)%"
    global_config:
      authHeader:
        Content-Type: application/authheader
        Authorization: 'Bearer Token'
      query: []
      request: []
      header:
        Content-Type: application/header
        Accept: application/headaadsa
      response: []
      isAuth: true
      isPaginate: true
      isHidden: false
```

### Generate TypeScript Client
__View Documentation:__ http:://127.0.0.1:8000/thor
```shell
bin/console thor:extract ./path # Generate Documentation to Directory
```

### Create Api Response

```php
use \Cesurapp\ApiBundle\AbstractClass\ApiController;
use \Cesurapp\ApiBundle\Response\ApiResponse;
use \Cesurapp\ApiBundle\Thor\Attribute\Thor;
use \Symfony\Component\Routing\Annotation\Route;

class TestController extends ApiController {
    #[Thor(
        stack: 'Login|1',
        title: 'Login EndPoint',
        info: "Description",
        request: [
            'username' => 'string',
            'password' => 'string',
        ],
        response: [
            200 => ['data' => UserResource::class],
            BadCredentialsException::class,
            TokenExpiredException::class,
            AccessDeniedException::class
        ],
        dto: LoginDto::class, 
        isAuth: false, 
        isPaginate: false, 
        order: 0
    )]
    #[Route(name: 'Login', path: '/login', methods: ['POST'])]
    public function getMethod(LoginDto $loginDto): ApiResponse {
        return ApiResponse::create()
            ->setData(['custom-data'])
            ->setQuery('QueryBuilder')
            ->setHTTPCache(60)  // Enable HTTP Cache
            ->setPaginate()     // Enable QueryBuilder Paginator
            ->setHeaders([])    // Custom Header
            ->setResource(UserResource::class)
    }
    
    #[Thor(
        stack: 'Profile|2',
        title: 'Profile EndPoint',
        query: [
            'name' => '?string',
            'filter' => [
                'id' => '?int',
                'name' => '?string',
                'fullName' => '?string',
            ],
        ],
        response: [200 => ['data' => UserResource::class]],
        isAuth: true, 
        isPaginate: false, 
        order: 0
    )]
    #[Route(name: 'GetExample', path: '/get', methods: ['GET'])]
    public function postMethod(): ApiResponse {
        $query = $userRepo->createQueryBuilder('q');
        
        return ApiResponse::create()
            ->setQuery($query)
            ->setPaginate()     // Enable QueryBuilder Paginator
            ->setHeaders([])    // Custom Header
            ->setResource(UserResource::class)
    }
}
```

### Create Api Resource
Filter and DataTable only work when pagination is enabled. Automatic TS columns are created for the table.
Export is automatically enabled for all tables.

```php
use \Cesurapp\ApiBundle\Response\ApiResourceInterface;

class UserResource implements ApiResourceInterface {
    public function toArray(mixed $item, mixed $optional = null): array {
        return [
            'id' => $object->getId(),
            'name' => $object->getName()
        ]
    }
    
    public function toResource(): array {
        return [
              'id' => [
                'type' => 'string', // Typescript Type -> ?string|?int|?boolean|?array|?object|NotificationResource::class|
                'filter' => static function (QueryBuilder $builder, string $alias, mixed $data) {}, // app.test?filter[id]=test
                'table' => [ // Typescript DataTable Types
                    'label' => 'ID',                     // DataTable Label
                    'sortable' => true,                  // DataTable Sortable Column   
                    'sortable_default' => true,          // DataTable Default Sortable Column
                    'sortable_desc' => true,             // DataTable Sortable DESC
                    'filter_input' => 'input',           // DataTable Add Filter Input Type -> input|number|date|daterange|checkbox|country|language
                   
                    // These fields are used in the backend. It doesn't transfer to the frontend. 
                    'exporter' => static fn($v) => $v,   // Export Column Template
                    'sortable_field' => 'firstName',     // Doctrine Getter Method
                    'sortable_field' => static fn (QueryBuilder $builder, string $direction) => $builder->orderBy('u.firstName', $direction),
                ],
            ],
            'created_at' => [
                'type' => 'string',
                'filter' => [
                    'from' => static function (QueryBuilder $builder, string $alias, mixed $data) {}, // app.test?filter[created_at][min]=test
                    'to' => static function (QueryBuilder $builder, string $alias, mixed $data) {}, // app.test?filter[created_at][max]=test
                ]
            ]
        ]   
    }

}
```

__Using Filter__

Filters are set according to the query parameter. Only matching records are filtered.

Sample request `http://example.test/v1/userlist?filter[id]=1&filter[createdAt][min]=10.10.2023`

### Create Form Validation
Backend dates are stored in UTC ATOM format. In GET requests you get dates in ATOM format.
In POST|PUT requests, send dates in ATOM format, converted to UTC.

```php
use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Cesurapp\ApiBundle\Thor\Attribute\ThorResource;
use Symfony\Component\Validator\Constraints as Assert;

class LoginDto extends ApiDto {
    /**
     * Enable Auto Validation -> Default Enabled
     */
    protected bool $auto = true;
    
    /**
     * Form Fields
     */
    #[Assert\NotNull]
    public string|int|null|bool $name;

    #[Assert\Length(min: 3, max: 100)]
    public ?string $lastName;

    #[Assert\Length(min: 10, max: 100)]
    #[Assert\NotNull]
    public int $phone;

    #[Assert\NotNull]
    #[Assert\GreaterThan(new \DateTimeImmutable())]
    public \DateTimeImmutable $send_at;
    
    #[Assert\Optional([
        new Assert\Type('array'),
        new Assert\Count(['min' => 1]),
        new Assert\All([
            new Assert\Collection([
                'slug' => [
                    new Assert\NotBlank(),
                    new Assert\Type(['type' => 'string']),
                ],
                'label' => [
                    new Assert\NotBlank(),
                ],
            ]),
        ]),
    ])]
    #[ThorResource(data: [[
        'slug' => 'string',
        'label' => 'string|int|boolean',
    ]])]
    public ?array $data;
}
```