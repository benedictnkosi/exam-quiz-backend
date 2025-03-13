

generate getters and setters php bin/console make:entity --regenerate

symfony server:start

symfony server:start --no-tls --allow-http --port=8000 --allow-all-ip

### migrate changes to database
php bin/console doctrine:migrations:diff

php bin/console doctrine:migrations:migrate --no-interaction


## on server
php bin/console doctrine:migrations:migrate



2. Then you can use the make:entity command to update your entity:

composer require --dev symfony/maker-bundle

Or, to update a specific entity:

php bin/console make:entity --regenerate


### remove tracked file from git
git rm --cached .env
git add .gitignore
git commit -m "Remove .env from git tracking"