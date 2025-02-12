php bin/console doctrine:mapping:import --force "App\Entity" annotation --path=src/Entity

generate getters and setters php bin/console make:entity --regenerate

symfony server:start