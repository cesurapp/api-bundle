## 1. API Resources

### Purpose
Resources transform entities/objects into API-compatible arrays and define filtering/sorting behavior for paginated endpoints.

### When to Create
- When returning entities in API responses
- When implementing filtering or sorting on paginated endpoints
- When DataTable functionality is required

### Naming Convention
`{Entity}Resource` (e.g., `UserResource`, `ProductResource`)

### Implementation
Implement `Cesurapp\ApiBundle\Response\ApiResourceInterface`:

```php
use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Doctrine\ORM\QueryBuilder;

class UserResource implements ApiResourceInterface
{
    public function toArray(mixed $item, mixed $optional = null): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'email' => $item->getEmail(),
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
                    'filter_input' => 'input',
                ],
            ],
            'name' => [
                'type' => 'string',
                'filter' => static function (QueryBuilder $builder, string $alias, mixed $data) {
                    $builder->andWhere("$alias.name LIKE :name")->setParameter('name', "%$data%");
                },
                'table' => [
                    'label' => 'Name',
                    'sortable' => true,
                    'filter_input' => 'input',
                ],
            ],
        ];
    }
}
```

### Usage in Controllers
```php
return ApiResponse::create()
    ->setQuery($queryBuilder)
    ->setPaginate()
    ->setResource(UserResource::class);
```

---

## 2. API Responses (Controller & Route)

### Standard Pattern
Controllers extend `ApiController` and return `ApiResponse` instances.

### Controller Structure
```php
use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends ApiController
{
    #[Thor(
        stack: 'User|1',
        title: 'Get User List',
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

### ApiResponse Methods
- `setData(mixed $data)` - Set response data
- `setQuery(QueryBuilder $query)` - Set Doctrine query for pagination/filtering
- `setPaginate(?int $max = 20)` - Enable pagination
- `setResource(string $class)` - Apply resource transformation
- `setCode(int $code)` - Set HTTP status code
- `setHeaders(array $headers)` - Set custom headers
- `setHTTPCache(int $lifetime)` - Enable HTTP caching
- `addMessage(string $message, MessageType $type)` - Add translatable message

### Route Definition
Use standard Symfony routing attributes with `#[Thor]` for documentation generation.

---

## 3. DTO (Data Transfer Objects)

### Purpose
DTOs validate and type-cast incoming request data (query params, request body, files).

### When Required
- All POST/PUT/PATCH endpoints that accept user input
- When validation is needed
- When type safety is required for input data

### Naming Convention
`{Action}Dto` (e.g., `CreateUserDto`, `LoginDto`, `UpdateProductDto`)

### Implementation
Extend `Cesurapp\ApiBundle\AbstractClass\ApiDto`:

```php
use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDto extends ApiDto
{
    #[Assert\NotNull]
    #[Assert\Email]
    public string $email;

    #[Assert\NotNull]
    #[Assert\Length(min: 8)]
    public string $password;

    #[Assert\NotNull]
    public string $name;

    public ?int $age = null;
}
```

### Usage in Controllers
```php
#[Route('/users', methods: ['POST'])]
public function create(CreateUserDto $dto): ApiResponse
{
    $user = new User();
    $user->setEmail($dto->email);
    $user->setPassword($dto->password);

    // Or use validated data array
    $validated = $dto->validated();

    return ApiResponse::create()->setData($user);
}
```

### Key Features
- **Auto-validation**: Runs on construction by default (`protected bool $auto = true`)
- **Auto-mapping**: Request data automatically mapped to public properties
- **Type casting**: Automatic conversion to declared types (int, string, bool, DateTime, enums)
- **PUT method handling**: `$id` automatically injected from route parameters on PUT requests

### Getting Validated Data
```php
$dto->validated();           // Returns all validated fields as array
$dto->validated('email');    // Returns specific field value
$dto->email;                 // Direct property access
```

---

## 4. Form Validation

### Where Validation Lives
Validation constraints are defined directly on DTO properties using Symfony Validator attributes.

### Validation Flow
1. DTO constructed with Request and ValidatorInterface injected
2. Request data auto-mapped to DTO properties
3. Validation runs automatically (if `$auto = true`)
4. On failure: `ValidationException` thrown with HTTP 422
5. On success: DTO ready for use

### Validation Error Response
Errors returned as:
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["This value is not a valid email address."],
    "password": ["This value is too short. It should have 8 characters or more."]
  }
}
```

### Complex Validation Example
```php
use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateSettingsDto extends ApiDto
{
    #[Assert\NotNull]
    public string $userId;

    #[Assert\Optional([
        new Assert\Type('array'),
        new Assert\All([
            new Assert\Collection([
                'key' => [new Assert\NotBlank(), new Assert\Type('string')],
                'value' => [new Assert\NotBlank()],
            ])
        ])
    ])]
    public ?array $settings = null;
}
```

### Custom Validation
Override lifecycle methods:

```php
protected function beforeValidated(): void
{
    // Custom logic before validation
    if ($this->email) {
        $this->email = strtolower($this->email);
    }
}

protected function endValidated(): void
{
    // Custom logic after successful validation
}
```

### Manual Validation
```php
protected bool $auto = false;

// In controller
if (!$dto->validate(throw: false)) {
    // Handle validation failure
}
```

---

## 5. TypeScript Generator

### Purpose
Generates TypeScript types, API client code, and DataTable schemas from PHP code.

### Source of Truth
- **Request/Response Types**: `#[Thor]` attribute definitions
- **API Structure**: Controller routes and DTOs
- **DataTable Schema**: `ApiResourceInterface::toResource()` configurations
- **Resource Types**: DTO properties with `#[ThorResource]` attribute

### When Regeneration Required
- After adding/modifying API endpoints
- After changing DTO properties
- After updating Resource `toResource()` definitions
- After modifying `#[Thor]` attributes

### Command
```bash
bin/console thor:extract ./output-directory
```

### Generated Output
- TypeScript API client with typed methods
- Request/Response interfaces
- DataTable column schemas
- Filter/sort configurations

### Configuration
Define global defaults in `config/packages/api.yaml`:

```yaml
api:
  thor:
    base_url: "%env(APP_DEFAULT_URI)%"
    global_config:
      authHeader:
        Authorization: 'Bearer Token'
      isAuth: true
      isPaginate: false
```

### Documentation Viewer
Available at: `http://localhost:8000/thor`

---

## 6. Conventions & Rules

### Naming Conventions
- **Resources**: `{Entity}Resource` (e.g., `UserResource`)
- **DTOs**: `{Action}Dto` (e.g., `CreateUserDto`, `LoginDto`)
- **Controllers**: Extend `ApiController`, return `ApiResponse`

### Type Conventions
- **TypeScript types in Resources**: `'string' | '?string' | 'int' | '?int' | 'boolean' | '?boolean' | 'array' | 'object' | ResourceClass::class`
- **Date handling**: Backend uses UTC ATOM format; send/receive dates in ATOM format
- **Query parameters**: Use `?` prefix for optional types (e.g., `'name' => '?string'`)

### Do's
- Always extend `ApiController` for API controllers
- Always return `ApiResponse` from controller methods
- Use `#[Thor]` attribute for API documentation
- Define validation on DTO properties, not in controllers
- Use Resources for entity serialization
- Enable pagination with `setPaginate()` when returning collections
- Use `toResource()` for filter/sort definitions

### Don'ts
- Don't manually validate request data in controllers
- Don't return arrays directly from controllers
- Don't implement custom serialization logic in controllers
- Don't bypass DTO validation (`$auto = true` is default for a reason)
- Don't use Resources for non-entity data structures

### Filter Input Types
Available `filter_input` values in `toResource()`:
- `input` - Text input
- `number` - Number input
- `range` - Number range (requires `from`/`to` filter structure)
- `date` - Date picker
- `daterange` - Date range (requires `from`/`to` filter structure)
- `checkbox` - Boolean checkbox
- `country` - Country selector
- `language` - Language selector
- `currency` - Currency selector

### Common Mistakes
- Forgetting to call `setPaginate()` when pagination is expected
- Not defining `toResource()` when filters/sorting are needed
- Using wrong TypeScript type format in Resource definitions
- Not regenerating TypeScript after API changes
- Mixing business logic into DTOs (use `initObject()` or service layer instead)
- Forgetting `#[Thor]` attribute for documentation generation
