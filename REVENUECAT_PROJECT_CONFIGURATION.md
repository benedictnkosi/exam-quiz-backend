# RevenueCat Project Configuration

This document explains how to configure RevenueCat API keys and project IDs for different projects in the exam-quiz-backend application.

## Overview

The application now supports multiple RevenueCat projects (e.g., MATHS, READS) with project-specific API keys and project IDs. The configuration is dynamic and based on the `projectName` parameter passed to the subscription API endpoint.

## Environment Variables

Add the following environment variables to your `.env` file:

### For READS Project
```env
REVENUECAT_V2_READS_API_KEY=your_reads_api_key_here
REVENUECAT_V2_READS_PROJECT_ID=your_reads_project_id_here
```

### For MATHS Project
```env
REVENUECAT_V2_MATHS_API_KEY=your_maths_api_key_here
REVENUECAT_V2_MATHS_PROJECT_ID=your_maths_project_id_here
```

### For Additional Projects
For any new project (e.g., SCIENCE), follow the same pattern:
```env
REVENUECAT_V2_SCIENCE_API_KEY=your_science_api_key_here
REVENUECAT_V2_SCIENCE_PROJECT_ID=your_science_project_id_here
```

## API Usage

The subscription endpoint now accepts a `projectName` parameter that determines which RevenueCat project credentials to use:

```
POST /api/subscription/{projectName}
```

### Examples:
- `POST /api/subscription/MATHS` - Uses MATHS project credentials
- `POST /api/subscription/READS` - Uses READS project credentials
- `POST /api/subscription/SCIENCE` - Uses SCIENCE project credentials

## Implementation Details

The `SubscriptionService::updateRevenueCatSubscription()` method now:
1. Dynamically constructs environment variable names based on the `projectName`
2. Retrieves the project-specific API key and project ID
3. Uses these credentials for RevenueCat API calls
4. Logs which project credentials are being used

## Error Handling

If the required environment variables are not found for a specific project, the application will throw an exception with a clear error message indicating which project is missing configuration.

## Migration from Old Configuration

If you were previously using a single `REVENUECAT_API_KEY` environment variable, you'll need to:
1. Create project-specific environment variables as shown above
2. Remove the old `REVENUECAT_API_KEY` variable (if no longer needed)
3. Update any deployment scripts or CI/CD configurations to include the new variables 