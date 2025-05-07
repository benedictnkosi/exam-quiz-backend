

generate getters and setters php bin/console make:entity --regenerate

symfony server:start

symfony server:start --no-tls --allow-http --port=8000 --allow-all-ip

### migrate changes to database
php bin/console doctrine:migrations:generate

php bin/console doctrine:migrations:migrate

php bin/console doctrine:schema:validate

php bin/console doctrine:migrations:diff

php bin/console doctrine:schema:update --force

## on server
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate

FK_5AF0C879FEF0481D

2. Then you can use the make:entity command to update your entity:

composer require --dev symfony/maker-bundle

Or, to update a specific entity:

php bin/console make:entity --regenerate


### remove tracked file from git
git rm --cached .env
git add .gitignore
git commit -m "Remove .env from git tracking"


### Working with the server

/var/www/exam-quiz-backend

copy files over
scp ./test.sql root@examquiz.dedicated.co.za:/var/www/exam-quiz-backend/public/assets/images/learnMzansi/

copy logs
scp root@examquiz.dedicated.co.za:/var/www/exam-quiz-backend/var/log/dev.log ./dev.log

scp root@examquiz.dedicated.co.za:/var/www/exam-quiz-backend/exam_quiz-2025-03-29_10-32-11.sql.gz ./exam_quiz-2025-03-29_10-32-11.sql.gz
exam_quiz-2025-03-29_10-32-11.sql.gz

run sql in file
mysql -u root -p exam_quiz < app_question.sql

restart fpm
sudo systemctl restart php8.3-fpm.service


Git commit and merge
git merge --no-ff -m "Merge branch"
cd /var/www/exam-quiz-backend && git pull && git merge --no-ff -m "Merge branch"

### manual script to send todos 
php bin/console app:send-todo-notifications

### To see the cron jobs scheduled for the current user, run the following command in the terminal:
crontab -l

### IP config
ipconfig getifaddr en0

## modify cron jobs
DITOR=nano crontab -e

### view cron jobs
sudo crontab -u www-data -l
sudo crontab -u www-data -e

### running deepsick
ollama serve
ollama run deepseek-llm

ollama run deepseek-r1

### After deploying changes
First, clear the cache:
php bin/console cache:clear

php bin/console cache:warmup

php bin/console doctrine:migrations:migrate

mkdir -p public/assets/lectures
chmod 777 public/assets/lectures

php bin/console list


### Podcast
php bin/console app:generate-question-topics

php bin/console app:populate-topics
php bin/console app:generate-lecture

php bin/console app:record-lecture



### convert opus to m4a
chmod +x scripts/convert-opus-to-m4a.sh
chmod +x scripts/fiels-to-convert-count.sh
cd public/assets/lectures/
../../../scripts/convert-opus-to-m4a.sh
../../../scripts/fiels-to-convert-count.sh

php bin/console app:generate-question-topics 1  # For grade 1
