# Rules for this project

## Documentation
When asked to update documentation (or anything similar — "update docs", "document this", etc.), update the README.md file with the features added in the current PR/branch.

## Interfaces
Every new class (entity, controller, service, repository, event listener, ...) must have its own interface.
The interface is placed in the same directory or in an `Interface/` subdirectory.

## No `final` classes
Never use the `final` keyword on any class.
The project must remain extensible — plugin users need to be able to extend classes and modify behavior.

## Service arguments — named, not positional
In `services.yaml` the `arguments:` section must always use named arguments matching the constructor parameter names, e.g.:

```yaml
arguments:
    $customerRepository: '@...'
    $adminRepository: '@...'
```

Never use positional arguments (`- '@...'`). Names must exactly match the `__construct()` parameters of the class.

## No inline FQCN — always `use` imports
Never reference classes by their fully qualified name inline in PHP code (e.g. `throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException()`, `: \Some\Namespace\Thing`, `is_a($x, 'Some\\Namespace\\Thing')`).
Always add a `use` statement at the top of the file and reference the short class name:

```php
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

throw new NotFoundHttpException();
```

Applies to `throw new …`, `new …`, parameter/return type hints, `instanceof`, class-string references, and anywhere else a class is named. Use `instanceof` with an imported class, not `is_a()` with a namespace string.
