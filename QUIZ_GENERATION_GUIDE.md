# Quiz Generation Guide

## Overview

The Genre Story generation system now includes automatic quiz generation for each chapter. This feature creates 5 multiple-choice questions per chapter to test reading comprehension and engagement.

## Features

- **5 questions per chapter** with 4 multiple-choice options each
- **Age-appropriate content** tailored to different reading levels (7-10, 11-14, 15-18)
- **Comprehensive coverage** testing plot, characters, setting, and vocabulary
- **JSON format storage** for easy integration with frontend applications
- **Explanation for each answer** to help learners understand the reasoning

## Database Schema

### New Field Added

```sql
ALTER TABLE `genre_story` 
ADD COLUMN `quiz` JSON DEFAULT NULL AFTER `vocabulary`;
```

### Quiz JSON Structure

```json
{
  "questions": [
    {
      "question": "What is the main character's name?",
      "options": ["Alice", "Bob", "Charlie", "David"],
      "correct_answer": 0,
      "explanation": "The main character is introduced as Alice in the first paragraph."
    }
  ]
}
```

## Command Usage

### Basic Quiz Generation

```bash
# Generate stories with quizzes for all plots
php bin/console app:generate-genre-stories --generate-quiz

# Generate stories with quizzes for a specific plot
php bin/console app:generate-genre-stories --plot 1 --generate-quiz

# Generate stories with quizzes for a specific genre
php bin/console app:generate-genre-stories --genre "Adventure" --generate-quiz

# Generate stories with quizzes for a specific age group
php bin/console app:generate-genre-stories --age-group "7-10" --generate-quiz

# Using the shortcut option
php bin/console app:generate-genre-stories -z
```

### Combined with Other Features

```bash
# Generate stories with both images and quizzes
php bin/console app:generate-genre-stories --generate-images --generate-quiz

# Generate stories with quizzes and replace existing content
php bin/console app:generate-genre-stories --generate-quiz --replace

# Generate stories with custom chapter count and quizzes
php bin/console app:generate-genre-stories --chapters 3 --generate-quiz
```

## Quiz Question Types

The AI generates questions that cover:

1. **Character Understanding** - Names, traits, motivations
2. **Plot Comprehension** - Events, conflicts, resolutions
3. **Setting Recognition** - Locations, environments, atmosphere
4. **Vocabulary Testing** - Key words and phrases from the story
5. **Theme Identification** - Lessons, messages, moral values

## Age Group Adaptations

### 7-10 Age Group
- Simple, direct questions
- Basic vocabulary testing
- Focus on concrete story elements
- Clear, unambiguous answers

### 11-14 Age Group
- Moderate complexity questions
- Intermediate vocabulary
- Character development focus
- Some inference-based questions

### 15-18 Age Group
- Sophisticated question structure
- Advanced vocabulary testing
- Theme and symbolism questions
- Critical thinking elements

## API Integration

### Retrieving Quiz Data

```php
// Get a story with quiz data
$story = $genreStoryRepository->find($storyId);
$quiz = $story->getQuiz();

if ($quiz && isset($quiz['questions'])) {
    $questions = $quiz['questions'];
    // Process quiz questions
}
```

### Quiz Validation

```php
// Validate quiz structure
function validateQuiz($quiz) {
    if (!isset($quiz['questions']) || !is_array($quiz['questions'])) {
        return false;
    }
    
    foreach ($quiz['questions'] as $question) {
        if (!isset($question['question']) || 
            !isset($question['options']) || 
            !isset($question['correct_answer']) || 
            !isset($question['explanation'])) {
            return false;
        }
    }
    
    return true;
}
```

## Frontend Implementation Example

```javascript
// Example quiz display component
function displayQuiz(quizData) {
    if (!quizData || !quizData.questions) {
        return 'No quiz available';
    }
    
    return quizData.questions.map((question, index) => `
        <div class="quiz-question">
            <h3>Question ${index + 1}</h3>
            <p>${question.question}</p>
            <div class="options">
                ${question.options.map((option, optionIndex) => `
                    <label>
                        <input type="radio" name="q${index}" value="${optionIndex}">
                        ${option}
                    </label>
                `).join('')}
            </div>
            <div class="explanation" style="display: none;">
                <strong>Explanation:</strong> ${question.explanation}
            </div>
        </div>
    `).join('');
}
```

## Error Handling

### Common Issues

1. **Quiz JSON Parsing Failed**
   - Check AI response format
   - Verify JSON structure
   - Ensure all required fields are present

2. **Insufficient Questions**
   - AI may generate fewer than 5 questions
   - Check chapter content quality
   - Verify prompt instructions

3. **Invalid Correct Answer Index**
   - Ensure correct_answer is 0-3 (for 4 options)
   - Validate option count matches correct_answer range

### Debugging

```bash
# Enable verbose output to see parsing details
php bin/console app:generate-genre-stories --generate-quiz -v

# Check specific chapter generation
php bin/console app:generate-genre-stories --plot 1 --age-group "7-10" --chapters 1 --generate-quiz
```

## Best Practices

1. **Test with Small Batches** - Start with single plots or age groups
2. **Validate Quiz Quality** - Review generated questions for accuracy
3. **Monitor AI Responses** - Check for consistent formatting
4. **Backup Before Replacement** - Use --replace carefully
5. **Age-Appropriate Content** - Ensure questions match reading level

## Migration Notes

### Existing Stories
- Stories without quizzes will have `quiz` field set to `NULL`
- Use `--replace` flag to regenerate existing stories with quizzes
- Quiz generation is optional and doesn't affect existing functionality

### Database Updates
- Run the SQL migration to add the quiz field
- Existing stories remain functional without quiz data
- New stories will include quiz data when `--generate-quiz` is used

## Future Enhancements

- **Difficulty Levels** - Easy, Medium, Hard questions
- **Question Categories** - Plot, Character, Setting, Vocabulary
- **Scoring System** - Points per question based on difficulty
- **Progress Tracking** - Quiz completion and performance metrics
- **Custom Questions** - Manual quiz creation interface 