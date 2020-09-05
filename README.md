# Review Reminder

![phpcs](https://github.com/DominicWatts/ReviewReminder/workflows/phpcs/badge.svg)

![PHPCompatibility](https://github.com/DominicWatts/ReviewReminder/workflows/PHPCompatibility/badge.svg)

![PHPStan](https://github.com/DominicWatts/ReviewReminder/workflows/PHPStan/badge.svg)

![PHPUnit](https://github.com/DominicWatts/ReviewReminder/workflows/PHPUnit/badge.svg)

[![Coverage Status](https://coveralls.io/repos/github/DominicWatts/ReviewReminder/badge.svg)](https://coveralls.io/github/DominicWatts/ReviewReminder)

Send email to remind customers to review products

# Install instructions

`composer require dominicwatts/reviewreminder`

`php bin/magento setup:upgrade`

`php bin/magento setup:di:compile`

# Usage instructions

## Console Command

    php bin/magento xigen:reviewreminder:remind

## Cron

    Toggle cron option in config

# Admin Configuration

![Screenshot](https://i.snipboard.io/6uh9RC.jpg)

# Email Template

![Screenshot](https://i.snipboard.io/pgHqer.jpg)

# Notes

[Coveralls Status](https://coveralls.io/github/DominicWatts/ReviewReminder)