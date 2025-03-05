

generate getters and setters php bin/console make:entity --regenerate

symfony server:start

2. Then you can use the make:entity command to update your entity:

composer require --dev symfony/maker-bundle

Or, to update a specific entity:

php bin/console make:entity --regenerate


### remove tracked file from git
git rm --cached .env
git add .gitignore
git commit -m "Remove .env from git tracking"