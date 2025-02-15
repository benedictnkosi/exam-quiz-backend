php bin/console doctrine:mapping:import --force "App\Entity" annotation --path=src/Entity

generate getters and setters php bin/console make:entity --regenerate

symfony server:start

2. Then you can use the make:entity command to update your entity:

composer require --dev symfony/maker-bundle

Or, to update a specific entity:

php bin/console make:entity --regenerate