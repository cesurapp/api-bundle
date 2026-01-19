# Symfony Api Bundle

[![App Tester](https://github.com/cesurapp/api-bundle/actions/workflows/testing.yaml/badge.svg)](https://github.com/cesurapp/api-bundle/actions/workflows/testing.yaml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?logo=Unlicense)](LICENSE.md)

This package allows you to expose fast API endpoints with Symfony.

**Features:**
* JSON request body transformer
* Error messages collected under a single format
* Language translation applied to all error messages
* Custom CORS header support
* Automatic documentation generator (Thor)
* TypeScript client generator
* API DTO resolver with auto-validation
* Doctrine filter & sorter resource
* PhoneNumber, UniqueEntity, Username validators
* Excel, CSV exporter (Sonata Export Bundle)

**Documentation:**
* [GUIDELINES.md](GUIDELINES.md) - Comprehensive usage guide for developers and AI agents

## Installation

**Requirements:** Symfony 8+, PHP 8.1+

```shell
composer require cesurapp/api-bundle
```

## Configuration

Create `config/packages/api.yaml`:
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

## TypeScript Client Generation

**View Documentation:** http://127.0.0.1:8000/thor

```shell
bin/console thor:extract ./output-directory
```

## Usage Examples

### Basic Controller with POST Endpoint

```php
use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends ApiController
{
    #[Thor(
        stack: 'User|1',
        title: 'Create User',
        info: 'Creates a new user account',
        request: [
            'email' => 'string',
            'password' => 'string',
            'name' => 'string',
        ],
        response: [
            200 => ['data' => UserResource::class],
        ],
        dto: CreateUserDto::class,
        isAuth: false,
        isPaginate: false
    )]
    #[Route('/users', methods: ['POST'])]
    public function create(CreateUserDto $dto): ApiResponse
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($dto->password);
        $user->setName($dto->name);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ApiResponse::create()
            ->setData($user)
            ->setResource(UserResource::class);
    }

    #[Thor(
        stack: 'User|2',
        title: 'List Users',
        query: [
            'filter' => [
                'name' => '?string',
                'email' => '?string',
            ],
        ],
        response: [200 => ['data' => UserResource::class]],
        isAuth: true,
        isPaginate: true
    )]
    #[Route('/users', methods: ['GET'])]
    public function list(UserRepository $repo): ApiResponse
    {
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u'))
            ->setPaginate()
            ->setResource(UserResource::class);
    }
}
```

### API Resource

**Purpose:** Transform entities to API responses and define filtering/sorting behavior.

**Note:** Filters and DataTable features only work when pagination is enabled.

```php
use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Doctrine\ORM\QueryBuilder;

class UserResource implements ApiResourceInterface
{
    public function toArray(mixed $item, mixed $optional = null): array
    {
        return [
            'id' => $item->getId(),
            'email' => $item->getEmail(),
            'name' => $item->getName(),
            'createdAt' => $item->getCreatedAt()->format(\DateTime::ATOM),
        ];
    }

    public function toResource(): array
    {
        return [
            'id' => [
                'type' => 'string',
                'filter' => static function (QueryBuilder $builder, string $alias, mixed $data) {
                    $builder->andWhere("$alias.id = :id")->setParameter('id', $data);
                },
                'table' => [
                    'label' => 'ID',
                    'sortable' => true,
                    'sortable_default' => true,
                    'sortable_desc' => true,
                    'filter_input' => 'input',
                ],
            ],
            'email' => [
                'type' => 'string',
                'filter' => static function (QueryBuilder $builder, string $alias, mixed $data) {
                    $builder->andWhere("$alias.email LIKE :email")
                        ->setParameter('email', "%$data%");
                },
                'table' => [
                    'label' => 'Email',
                    'sortable' => true,
                    'filter_input' => 'input',
                ],
            ],
            'createdAt' => [
                'type' => 'string',
                'filter' => [
                    'from' => static function (QueryBuilder $builder, string $alias, mixed $data) {
                        $builder->andWhere("$alias.createdAt >= :dateFrom")
                            ->setParameter('dateFrom', $data);
                    },
                    'to' => static function (QueryBuilder $builder, string $alias, mixed $data) {
                        $builder->andWhere("$alias.createdAt <= :dateTo")
                            ->setParameter('dateTo', $data);
                    },
                ],
                'table' => [
                    'label' => 'Created At',
                    'sortable' => true,
                    'filter_input' => 'daterange',
                ],
            ],
        ];
    }
}
```

**Using Filters:**

```
GET /users?filter[email]=john&filter[createdAt][from]=2024-01-01&filter[createdAt][to]=2024-12-31
```

### Data Transfer Object (DTO)

**Purpose:** Validate and type-cast incoming request data automatically.

**Date Format:** Backend uses UTC ATOM format. Send/receive dates in ATOM format.

```php
use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Cesurapp\ApiBundle\Thor\Attribute\ThorResource;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDto extends ApiDto
{
    #[Assert\NotNull]
    #[Assert\Email]
    public string $email;

    #[Assert\NotNull]
    #[Assert\Length(min: 8, max: 100)]
    public string $password;

    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 100)]
    public string $name;

    public ?int $age = null;

    #[Assert\NotNull]
    #[Assert\GreaterThan('now')]
    public ?\DateTimeImmutable $activatedAt = null;
}
```

**Complex Array Validation:**

```php
class UpdateSettingsDto extends ApiDto
{
    #[Assert\Optional([
        new Assert\Type('array'),
        new Assert\Count(['min' => 1]),
        new Assert\All([
            new Assert\Collection([
                'key' => [
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                ],
                'value' => [
                    new Assert\NotBlank(),
                ],
            ]),
        ]),
    ])]
    #[ThorResource(data: [[
        'key' => 'string',
        'value' => 'string|int|boolean',
    ]])]
    public ?array $settings = null;
}
```

**Validation Response (HTTP 422):**

```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["This value is not a valid email address."],
    "password": ["This value is too short. It should have 8 characters or more."]
  }
}
```

## ApiResponse Methods

| Method | Description |
|--------|-------------|
| `setData(mixed $data)` | Set response data |
| `setQuery(QueryBuilder $query)` | Set Doctrine query for pagination/filtering |
| `setPaginate(?int $max = 20)` | Enable pagination with optional max items per page |
| `setResource(string $class)` | Apply resource transformation |
| `setCode(int $code)` | Set HTTP status code (default: 200) |
| `setHeaders(array $headers)` | Set custom headers |
| `setHTTPCache(int $lifetime)` | Enable HTTP caching with lifetime in seconds |
| `addMessage(string $message, MessageType $type)` | Add translatable message |
| `addData(string $key, mixed $value)` | Add additional data to response |

## Advanced Features

### Custom Validation Hooks

```php
class CustomDto extends ApiDto
{
    protected function beforeValidated(): void
    {
        // Normalize data before validation
        if ($this->email) {
            $this->email = strtolower(trim($this->email));
        }
    }

    protected function endValidated(): void
    {
        // Additional logic after successful validation
    }
}
```

### Manual Validation Control

```php
class ManualDto extends ApiDto
{
    protected bool $auto = false; // Disable auto-validation
}

// In controller
$dto = new ManualDto($request, $validator);
if (!$dto->validate(throw: false)) {
    // Handle validation failure
}
```

### HTTP Caching

```php
return ApiResponse::create()
    ->setData($data)
    ->setHTTPCache(60, tags: ['user', 'profile']) // Cache for 60 seconds
    ->setResource(UserResource::class);
```

### Pagination with Custom Max

```php
return ApiResponse::create()
    ->setQuery($queryBuilder)
    ->setPaginate(max: 50, total: true) // 50 items per page, include total count
    ->setResource(UserResource::class);
```

**Pagination Response:**

```json
{
  "data": [...],
  "pager": {
    "max": 50,
    "prev": 1,
    "next": 3,
    "current": 2,
    "total": 150
  }
}
```

## Custom Validators

This bundle includes custom validators:

- `PhoneNumber` - Validates phone numbers
- `UniqueEntity` - Validates entity uniqueness in database
- `Username` - Validates username format

```php
use Cesurapp\ApiBundle\Validator\PhoneNumber;
use Cesurapp\ApiBundle\Validator\UniqueEntity;

class RegisterDto extends ApiDto
{
    #[Assert\NotNull]
    #[PhoneNumber]
    public string $phone;

    #[Assert\NotNull]
    #[UniqueEntity(entityClass: User::class, field: 'email')]
    public string $email;
}
```

## License

MIT License - see [LICENSE.md](LICENSE.md)