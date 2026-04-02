# Pravidla pro tento projekt

## Interfaces
Každá nová třída (entita, controller, service, repository, event listener, ...) musí mít vlastní interface.
Interface se umísťuje do stejné složky nebo do podsložky `Interface/`.

## Žádné `final` třídy
Nikdy nepoužívej klíčové slovo `final` u žádné třídy.
Projekt musí zůstat rozšiřitelný — uživatelé pluginu potřebují moci třídy extendovat a upravit chování.
