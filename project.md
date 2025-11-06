

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

### running deepseek
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



convert opus to m4a
chmod +x scripts/convert-opus-to-m4a.sh
chmod +x scripts/fiels-to-convert-count.sh
cd public/assets/lectures/
../../../scripts/convert-opus-to-m4a.sh
../../../scripts/fiels-to-convert-count.sh

php bin/console app:generate-question-topics 1  # For grade 1

### send message to a grade
php bin/console app:send-grade-message 12 "Accounting Quiz Added" "Download the latest version to start" "60"

mac1@Mac1s-MacBook-Pro SouthAfricanLanguages % crontab -l
0 23 * * * /Users/mac1/Documents/ExamQuiz/backups/backup_images.sh >> /Users/mac1/Documents/ExamQuiz/backups/images.log 2>&1
* * * * * /Users/mac1/Documents/ExamQuiz/backups/backup_db.sh >> /Users/mac1/Documents/ExamQuiz/backups/database.log 2>&1
0 15 * * * cd /Users/mac1/Documents/cursor/exam-quiz-admin && npx ts-node scripts/Grade12/populateDailyChat.ts >> logs/populateDailyChat.log 2>&1

### create stories
php bin/console app:generate-genre-stories --generate-images --generate-quiz

php bin/console  app:count-mands-in-sql


# News
# Search for Parliamentary ad hoc videos from the last 7 days (default)
php bin/console app:ewn-parliamentary-video --days=1 --avatar-id=d8190501dc7c4b77b852400a2008c984 --voice-id=007e1378fc454a9f976db570ba6164a7

# Find the newest Madlanga Commission video from last 7 days
php bin/console app:ewn-madlanga-video --days=1  --avatar-id=bb645f6e5a1b4407bc002967034f65e8 --voice-id=d41b5163f39044129d06aca88d7a8f4f

# Daily news4721

bin/console app:sabcdigital:scrape-prime-news

private string $defaultAvatarId = '93bf36d167184854bdde4ffb3b340981';
    private string $defaultVoiceId = 'QOdz6iaNL4YniX0zO8BV';


# run 4 hrs
php bin/console app:sabc-past-four-hours --avatar-id=93bf36d167184854bdde4ffb3b340981 --voice-id=QOdz6iaNL4YniX0zO8BV --upload-to-youtube