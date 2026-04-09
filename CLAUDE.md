# Rules for this project

## Interfaces
Every new class (entity, controller, service, repository, event listener, ...) must have its own interface.
The interface is placed in the same directory or in an `Interface/` subdirectory.

## No `final` classes
Never use the `final` keyword on any class.
The project must remain extensible — plugin users need to be able to extend classes and modify behavior.
