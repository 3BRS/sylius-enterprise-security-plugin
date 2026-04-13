# Rules for this project

## Documentation
When asked to update documentation (or anything similar — "update docs", "document this", etc.), update the README.md file with the features added in the current PR/branch.

## Interfaces
Every new class (entity, controller, service, repository, event listener, ...) must have its own interface.
The interface is placed in the same directory or in an `Interface/` subdirectory.

## No `final` classes
Never use the `final` keyword on any class.
The project must remain extensible — plugin users need to be able to extend classes and modify behavior.
