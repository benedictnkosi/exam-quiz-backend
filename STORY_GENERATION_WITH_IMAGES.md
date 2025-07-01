# Story Generation with Images - Complete Guide

## 📖 Overview

The Story Generation with Images system creates engaging, chaptered stories for different age groups with AI-generated illustrations. Each story consists of multiple chapters with strategic image placeholders and accumulative summaries that build upon previous chapters.

## 🎯 Features

- **Multi-chapter stories** (configurable, default: 2 chapters)
- **Age-appropriate content** (7-10, 11-14, 15-18 age groups)
- **Genre-specific storytelling** (Adventure, Fantasy, Mystery, Sci-Fi, Historical, Contemporary)
- **Strategic image placement** within narrative flow
- **AI-generated illustrations** using DALL-E 3
- **Accumulative summaries** for story continuity
- **Vocabulary tracking** and reading time calculation
- **Complete API endpoints** for frontend integration

## 🏗️ Architecture

### Database Schema

```sql
-- Genre Story Table
CREATE TABLE `genre_story` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plot_id` int(11) NOT NULL,
  `age_group` varchar(10) NOT NULL,
  `chapter_number` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `summary` text,
  `accumulative_summary` text,
  `word_count` int(11) DEFAULT NULL,
  `reading_time` int(11) DEFAULT NULL,
  `vocabulary` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plot_age_chapter` (`plot_id`, `age_group`, `chapter_number`)
);
```

### File Structure

```
public/assets/story-images/
├── story_1_7-10_ch1_img1_1704067200.png
├── story_1_7-10_ch1_img2_1704067201.png
├── story_1_11-14_ch1_img1_1704067202.png
├── story_1_11-14_ch1_img2_1704067203.png
├── story_2_7-10_ch1_img1_1704067204.png
└── ...
```

### Vocabulary Structure

```json
{
  "vocabulary": {
    "image_prompts": [
      "Detailed description for image 1",
      "Detailed description for image 2"
    ],
    "images": {
      "1": {
        "filename": "story_5_11-14_ch1_img1_1704067200.png",
        "prompt": "Enhanced prompt with context",
        "size": "512x512",
        "format": "png",
        "generatedAt": "2024-01-01 12:00:00"
      },
      "2": {
        "filename": "story_5_11-14_ch1_img2_1704067201.png",
        "prompt": "Enhanced prompt with context",
        "size": "512x512",
        "format": "png",
        "generatedAt": "2024-01-01 12:00:01"
      }
    },
    "vocabulary_words": ["word1", "word2", "word3"]
  }
}
```

## 🚀 Commands

### 1. Generate Stories with Images

```bash
# Generate stories with image placeholders and prompts
php bin/console app:generate-genre-stories --generate-images

# Generate for specific plot with images
php bin/console app:generate-genre-stories --plot=1 --generate-images

# Generate for specific genre and age group
php bin/console app:generate-genre-stories --genre="Adventure" --age-group="11-14" --generate-images

# Generate with custom number of chapters
php bin/console app:generate-genre-stories --chapters=3 --generate-images

# Replace existing stories
php bin/console app:generate-genre-stories --replace --generate-images
```

**Options:**
- `--plot, -p`: Generate for specific plot ID
- `--genre, -g`: Generate for all plots in a genre
- `--age-group, -a`: Generate for specific age group (7-10, 11-14, 15-18)
- `--replace, -r`: Replace existing stories
- `--chapters, -c`: Number of chapters per story (default: 2)
- `--generate-images, -i`: Include image placeholders and prompts

### 2. Generate Images from Prompts

```bash
# Generate images for all stories
php bin/console app:generate-story-images --save-to-disk

# Generate images for specific plot
php bin/console app:generate-story-images --plot-id=1 --save-to-disk

# Generate images for specific age group
php xd app:generate-story-images --age-group="7-10" --image-size="512x512"

# Generate images for specific story
php bin/console app:generate-story-images --story-id=123 --save-to-disk

# Generate images for specific chapter
php bin/console app:generate-story-images --chapter=1 --save-to-disk
```

**Options:**
- `--story-id, -s`: Generate for specific story ID
- `--plot-id, -p`: Generate for all stories in a plot
- `--age-group, -a`: Generate for specific age group
- `--chapter, -c`: Generate for specific chapter number
- `--replace, -r`: Replace existing images
- `--save-to-disk, -d`: Save images to filesystem
- `--image-size, -i`: Image size (512x512, 1024x1024, 1792x1024, 1024x1792) - default: 512x512

## 🎨 Image Placement Strategy

### Strategic Placement Rules

1. **First Image (`[IMAGE_PLACEHOLDER_1]`)**:
   - Early in the chapter
   - After introducing main setting or characters
   - Scene-setting moment

2. **Second Image (`[IMAGE_PLACEHOLDER_2]`)**:
   - Middle or later in the chapter
   - At moments of action, discovery, emotion, or dramatic tension
   - Climactic or emotionally charged scene

3. **Placement Guidelines**:
   - ❌ **Never** place both placeholders at the end
   - ❌ **Never** place placeholders together
   - ✅ Integrate naturally into narrative flow
   - ✅ Space placeholders appropriately (100+ characters apart)

### Age-Appropriate Image Styles

#### 7-10 Age Group
- Bright, cheerful colors
- Simple, clear compositions
- Friendly, approachable characters
- Soft, rounded shapes
- Whimsical and magical elements
- Safe, non-threatening imagery

#### 11-14 Age Group
- Dynamic and energetic compositions
- Rich, vibrant colors
- Detailed but not overwhelming
- Characters with personality and expression
- Atmospheric lighting and mood
- Adventure and discovery themes

#### 15-18 Age Group
- Sophisticated and atmospheric
- Nuanced color palettes
- Detailed and realistic elements
- Emotional depth and complexity
- Dramatic lighting and composition
- Mature themes while remaining appropriate

## 📚 API Endpoints

### Story Endpoints

```bash
# Get all stories
GET /api/genre-stories

# Get specific story
GET /api/genre-stories/{id}

# Get stories by plot
GET /api/genre-stories/plot/{plotId}

# Get stories by plot and age group
GET /api/genre-stories/plot/{plotId}/age-group/{ageGroup}

# Get specific chapter
GET /api/genre-stories/plot/{plotId}/age-group/{ageGroup}/chapter/{chapterNumber}

# Get complete story (all chapters)
GET /api/genre-stories/plot/{plotId}/age-group/{ageGroup}/complete

# Get random story
GET /api/genre-stories/random/{genreName}/{ageGroup}

# Get random complete story
GET /api/genre-stories/random/{genreName}/{ageGroup}/complete
```

### Image Endpoints

```bash
# Get images for specific story
GET /api/genre-stories/{id}/images

# Get image prompts for story
GET /api/genre-stories/{id}/image-prompts

# Get images for specific chapter
GET /api/genre-stories/plot/{plotId}/age-group/{ageGroup}/chapter/{chapterNumber}/images

# Get all images for complete story
GET /api/genre-stories/plot/{plotId}/age-group/{ageGroup}/complete/images

# Get random complete story with images
GET /api/genre-stories/random/{genreName}/{ageGroup}/complete/images
```

### Statistics Endpoints

```bash
# Get story statistics
GET /api/genre-stories/stats

# Get image statistics
GET /api/genre-stories/stats/images
```

## 📁 File Organization

### Generated Images Structure
```
public/assets/story-images/
├── story_1_7-10_ch1_img1_1704067200.png
├── story_1_7-10_ch1_img2_1704067201.png
├── story_1_11-14_ch1_img1_1704067202.png
├── story_1_11-14_ch1_img2_1704067203.png
├── story_2_7-10_ch1_img1_1704067204.png
└── ...
```

### Example Path
```
public/assets/story-images/story_5_11-14_ch1_img1_1704067200.png
```

## 🔧 Configuration

### Environment Variables
```env
# OpenAI API Configuration
OPENAI_API_KEY=your_openai_api_key
OPENAI_API_URL=https://api.openai.com/v1
```

### Image Generation Settings
- **Model**: DALL-E 3
- **Quality**: Standard
- **Sizes**: 512x512 (default), 1024x1024, 1792x1024, 1024x1792
- **Format**: PNG

## 📊 Monitoring and Validation

### Validation Features
- **Placement validation**: Checks if placeholders are at the end
- **Spacing validation**: Ensures placeholders aren't too close together
- **Warning system**: Alerts when placeholders are poorly placed

### Statistics Tracking
- Total stories and chapters
- Image coverage percentage
- Age group breakdown
- Genre breakdown
- Prompt and image counts

## 🎯 Use Cases

### 1. Educational Content Creation
- Generate age-appropriate reading materials
- Create visual learning aids
- Develop vocabulary-building stories

### 2. Children's Book Development
- Rapid prototyping of story concepts
- Visual storytelling enhancement
- Multi-age group content creation

### 3. Reading App Integration
- Progressive story delivery
- Interactive reading experiences
- Personalized content based on age and interests

### 4. Content Marketing
- Engaging social media content
- Educational blog posts
- Brand storytelling with visuals

## 🔄 Workflow Example

### Step 1: Generate Stories with Image Placeholders
```bash
php bin/console app:generate-genre-stories \
  --genre="Adventure" \
  --age-group="11-14" \
  --chapters=3 \
  --generate-images
```

### Step 2: Generate Images from Prompts
```bash
php bin/console app:generate-story-images \
  --plot-id=1 \
  --save-to-disk \
  --image-size="512x512"
```

### Step 3: Access via API
```bash
# Get complete story with images
curl "https://your-api.com/api/genre-stories/plot/1/age-group/11-14/complete/images"

# Get specific chapter with images
curl "https://your-api.com/api/genre-stories/plot/1/age-group/11-14/chapter/2/images"
```

## 🚨 Troubleshooting

### Common Issues

1. **Placeholders at End of Story**
   - Regenerate with updated prompts
   - Check validation warnings
   - Ensure proper AI model instructions

2. **Missing Image Prompts**
   - Run story generation with `--generate-images` flag
   - Check vocabulary field in database
   - Verify JSON structure

3. **Image Generation Failures**
   - Check OpenAI API key and quota
   - Verify image size parameters
   - Review prompt content for inappropriate content

4. **Poor Image Quality**
   - Adjust image size settings
   - Review and refine image prompts
   - Consider age-appropriate style guidelines

### Debug Commands
```bash
# Check story content and placeholders
php bin/console app:generate-genre-stories --plot=1 --generate-images --verbose

# Validate image placement
php bin/console app:generate-story-images --story-id=1 --dry-run

# Check statistics
curl "https://your-api.com/api/genre-stories/stats/images"
```

## 📈 Performance Considerations

### Optimization Tips
- **Batch processing**: Generate multiple stories at once
- **Caching**: Cache generated images and API responses
- **CDN**: Use CDN for image delivery
- **Database indexing**: Index frequently queried fields

### Resource Usage
- **API calls**: ~2 per chapter for story generation
- **Image generation**: ~2 per chapter for images
- **Storage**: ~0.5-1MB per story (512x512 images + metadata)
- **Processing time**: ~30-60 seconds per chapter

## 🔮 Future Enhancements

### Planned Features
- **Audio narration** generation
- **Interactive elements** (quizzes, activities)
- **Multi-language support**
- **Advanced image editing** capabilities
- **User feedback** integration
- **A/B testing** for story variations

### Integration Possibilities
- **Frontend frameworks** (React, Vue, Angular)
- **Mobile apps** (React Native, Flutter)
- **CMS systems** (WordPress, Drupal)
- **E-learning platforms** (Moodle, Canvas)

## 📞 Support

For technical support or feature requests:
- Check the troubleshooting section
- Review API documentation
- Test with sample data first
- Monitor system logs for errors

---

**Version**: 1.0.0  
**Last Updated**: January 2024  
**Compatibility**: Symfony 6.x, PHP 8.1+