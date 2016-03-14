# WP Slimstat to Piwik
Migrate your WP Slimstat Analytics visitor log to Piwik
## Installation
1. Open your preferred command line interface and change the directory to the folder of your web server you want the app to live in.
If you want the script to be available at http://yourserver.com/wp-slimstat-to-piwik/ and your server directories can be found under /var/www just type:
    ```
    cd /var/www/htdocs/
    git clone https://github.com/christianhennen/wp-slimstat-to-piwik.git
    ```
  to download all the necessary files. Type `git clone --help` for further information on cloning a repository.

2. Change to the cloned directory and install all dependencies via composer:
    ```
    cd wp-slimstat-to-piwik/
    composer install
    ```
  Make sure you are using the correct version of PHP with composer when working with management tools like Plesk.

3. Edit index.php (e.g. with `nano index.php`) and enter your Piwik and Slimstat configurations according to the next steps.

4. Piwik configuration:
    * PIWIK_URL - The full URL to your Piwik installation, e.g. `http://piwik.yourserver.com/`.
    * TOKEN - The auth_token of a super user of your Piwik installation, e.g. `12345678901234567890123456789012`. You can find this information in the settings area under Administration > Users.
    * SITE_ID - The ID of the site you want to track your visits to, e.g. `1`. You can find this information in the settings area under Administration > Websites.

    **IMPORTANT:** TOKEN needs to be set to the auth_token of a super user of your Piwik installation. Otherwise entries into the past will not be possible and the usage of this script pointless!

5. Slimstat configuration
    * WEBSITE_DOMAIN - The full URL to the Website you use Slimstat on, e.g. `http://yourserver.com/`.
    * DB_HOST' - The database host. In most cases the default setting `localhost` should be fine.
    * DB_USER - The database user.
    * DB_PASSWORD - The password of the database user.
    * DB_NAME - The name of the database your WordPress installation uses, e.g. `wordpress`;
    * TABLE_PREFIX - Defaults to 'wp_'. 
        * Only change if you set this value during or after WordPress installation or used any plugin to do so, e.g. iThemes Security.
    * OLD_VERSION - Defaults to 'false'. At some point, the database model of Slimstat changed.
        * If your Slimstat version hasn't been upgraded for a long time or if you deactivated the plugin before the changes in the database model, you should set this option to 'true'.
        * If your database doesn't contain any tables named ...slim_stats_3 or ...slim_stats_archive_3, you use an old version and need to set OLD_VERSION to 'true'. 

    **NOTE:** Except for OLD_VERSION, all these values can be found in the wp-config.php of your WordPress installation.
 
## Usage
Just type in the address you installed the script in into your browser, e.g. `http://yourserver.com/wp-slimstat-to-piwik/`.
The script will run any database upgrades automatically and start tracking every old visit stored in the Slimstat tables to Piwik.

## Credits
Original script by smickus: http://www.smickus.org/converting-from-wordpress-slimstat-to-piwik/

I modified it to make it more configurable and to reflect the database changes in WP Slimstat.