# Disable Inactive Commute Accounts Command

This command automatically disables commuter accounts that have one-sided conversations older than a specified number of hours.

## Overview

The command finds commute distance message tracking records where:
- Only one party (driver or passenger) sent a message
- The other party did not respond
- The conversation is older than the specified hours (default: 48 hours)

When such conversations are found, the command:
1. Disables the inactive party's account (sets status to 'inactive')
2. Sends a push notification informing them about the account pause
3. Logs all actions for audit purposes

## Command Usage

### Basic Usage

```bash
# Run with default settings (48 hours, max 100 accounts)
php bin/console app:disable-inactive-commute-accounts

# Run with custom hours
php bin/console app:disable-inactive-commute-accounts --hours=72

# Run with custom limit
php bin/console app:disable-inactive-commute-accounts --limit=50

# Run in dry-run mode (no actual changes)
php bin/console app:disable-inactive-commute-accounts --dry-run
```

### Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--dry-run` | Run without making actual changes (just show what would be done) | false |
| `--hours` | Number of hours to consider as inactive | 48 |
| `--limit` | Maximum number of accounts to process | 100 |

### Examples

```bash
# Test run - see what would be disabled without making changes
php bin/console app:disable-inactive-commute-accounts --dry-run --hours=24

# Process up to 200 accounts with 72-hour inactivity threshold
php bin/console app:disable-inactive-commute-accounts --hours=72 --limit=200

# Quick check for very recent inactivity (12 hours)
php bin/console app:disable-inactive-commute-accounts --hours=12 --limit=50
```

## Output

The command provides detailed output including:

- Number of one-sided conversations found
- Progress updates during processing
- Summary table with metrics
- Error details if any issues occur

### Example Output

```
Disable Inactive Commute Accounts
Checking for one-sided conversations older than 48 hours...

Found 15 one-sided conversations to process.
Processed 10/15
Processed 15/15

Summary
┌─────────────────┬───────┐
│ Metric          │ Count │
├─────────────────┼───────┤
│ Total Processed │ 15    │
│ Accounts Disabled│ 12    │
│ Notifications Sent│ 10   │
│ Errors          │ 3     │
└─────────────────┴───────┘

Successfully processed 15 conversations. 12 accounts disabled.
```

## Cron Job Setup

### 1. Create the Script

Make sure the script `scripts/disable_inactive_commute_accounts.sh` is executable:

```bash
chmod +x scripts/disable_inactive_commute_accounts.sh
```

### 2. Update the Script Path

Edit the script and update the `APP_DIR` variable to point to your application directory:

```bash
APP_DIR="/path/to/your/actual/app/directory"
```

### 3. Add to Crontab

Add a cron job to run the script regularly:

```bash
# Edit crontab
crontab -e

# Add one of these lines:
# Run every 6 hours
0 */6 * * * /path/to/your/app/scripts/disable_inactive_commute_accounts.sh

# Run daily at 2 AM
0 2 * * * /path/to/your/app/scripts/disable_inactive_commute_accounts.sh

# Run every 12 hours
0 */12 * * * /path/to/your/app/scripts/disable_inactive_commute_accounts.sh
```

## Implementation Notes

### Required Implementation

The command currently has placeholder methods that need to be implemented based on your actual data structure:

1. **CommuterRepository::findByCommuteId()** - Needs to be implemented to find commuters by commute ID
2. **DisableInactiveCommuteAccountsCommand::findInactiveCommuter()** - Needs to be implemented to map commute distances to commuters

### Data Structure Requirements

You need to establish a relationship between:
- `DriverPassengerDistance.driverCommuteId` → `Commuter`
- `DriverPassengerDistance.passengerCommuteId` → `Commuter`

### Implementation Options

1. **Add commute ID field to Commuter entity**
2. **Create a mapping table**
3. **Store commute IDs in a JSON field**
4. **Use a different identifier system**

### Example Implementation

If you add a `commuteId` field to the Commuter entity:

```php
// In CommuterRepository.php
public function findByCommuteId(int $commuteId): ?Commuter
{
    return $this->findOneBy(['commuteId' => $commuteId]);
}

// In DisableInactiveCommuteAccountsCommand.php
private function findInactiveCommuter(\App\Entity\DriverPassengerDistance $distance, string $inactiveParty): ?Commuter
{
    if ($inactiveParty === 'driver') {
        return $this->commuterRepository->findByCommuteId($distance->getDriverCommuteId());
    } else {
        return $this->commuterRepository->findByCommuteId($distance->getPassengerCommuteId());
    }
}
```

## Notification Message

When an account is disabled, the user receives a push notification with:

- **Title**: "Account Temporarily Paused"
- **Body**: "Your account has been paused due to inactivity. You haven't responded to a message for X hours. Contact support to reactivate your account."

## Logging

The command logs all actions to:
- Console output (for immediate feedback)
- Application logs (for audit trail)
- Script log file (if using the shell script)

## Safety Features

1. **Dry-run mode** - Test without making changes
2. **Limit option** - Prevent processing too many accounts at once
3. **Error handling** - Continues processing even if individual accounts fail
4. **Duplicate protection** - Won't disable already disabled accounts
5. **Comprehensive logging** - Full audit trail of all actions

## Monitoring

Monitor the command execution by:

1. **Checking logs**: `tail -f var/log/disable_inactive_accounts.log`
2. **Reviewing application logs**: Check your application's log files
3. **Database queries**: Monitor the `commuters` table for status changes
4. **Push notification delivery**: Check push notification service logs

## Troubleshooting

### Common Issues

1. **No accounts found**: Check if message tracking data exists
2. **Permission errors**: Ensure the script has proper permissions
3. **Database connection issues**: Verify database connectivity
4. **Push notification failures**: Check push notification service configuration

### Debug Mode

Run with verbose output for debugging:

```bash
php bin/console app:disable-inactive-commute-accounts --dry-run -v
```

## Security Considerations

1. **Rate limiting**: The command processes accounts in batches
2. **Audit trail**: All actions are logged
3. **Reversible**: Accounts can be reactivated manually
4. **Notification**: Users are informed when their account is disabled 