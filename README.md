This is a Composer-based installer for the [Lightning](https://www.drupal.org/project/lightning) Drupal distribution. Welcome to the future!

## Get Started
You will need the following installed:

* [Composer](https://getcomposer.org), obviously
* [Node](https://nodejs.org), which includes the NPM package manager

When you have those, run this command:
```
$ composer create-project acquia/lightning-project:^8.1.0 MY_PROJECT --no-interaction
```
Composer will create a new directory called MY_PROJECT containing a ```docroot``` directory with a full Lightning code base therein. You can then install it like you would any other Drupal site.

## Maintenance
```drush make```, ```drush pm-download```, ```drush pm-update``` and their ilk are the old-school way of maintaining your code base. Forget them. You're in Composer land now!

Let this handy table be your guide:

| Task                                            | Drush                                         | Composer                                          |
|-------------------------------------------------|-----------------------------------------------|---------------------------------------------------|
| Installing a contrib project (latest version)   | ```drush pm-download PROJECT```               | ```composer require drupal/PROJECT:8.*```         |
| Installing a contrib project (specific version) | ```drush pm-download PROJECT-8.x-1.0-beta3``` | ```composer require drupal/PROJECT:8.1.0-beta3``` |
| Updating all contrib projects and Drupal core   | ```drush pm-update```                         | ```composer update```                             |
| Updating a single contrib project               | ```drush pm-update PROJECT```                 | ```composer update drupal/PROJECT```              |
| Updating Drupal core                            | ```drush pm-update drupal```                  | ```composer update drupal/core```                 |

Not too tricky, eh?

The magic is that Composer, unlike Drush, is a *dependency manager*. If module ```foo-8.x-1.0``` depends on ```baz-8.x-3.2```, Composer will not let you update baz to ```8.x-3.3``` (or downgrade it to ```8.x-3.1```, for that matter). Drush has no concept of dependency management. If you've ever accidentally hosed a site because of dependency issues like this, you've probably already realized how valuable Composer can be.

But to be clear: **you still need Drush**. Tasks such as database updates (```drush updatedb```) are still firmly in Drush's province, and it's awesome at that stuff. This installer will install a copy of Drush (local to the project) in the ```bin``` directory.

**Composer is only responsible for maintaining the code base**.

## Source Control
If you peek at the ```.gitignore``` we provide, you'll see that certain directories, including all directories containing contributed projects, are excluded from source control. This might be a bit disconcerting if you're newly arrived from Planet Drush, but in a Composer-based project like this one, **you SHOULD NOT commit your installed dependencies to source control**.

When you set up the project, Composer will create a file called ```composer.lock```, which is a list of which dependencies were installed, and in which versions. **Commit ```composer.lock``` to source control!** Then, when your colleagues want to spin up their own copies of the project, all they'll have to do is run ```composer install```, which will install the correct versions of everything in ```composer.lock```.


## Task VI-466
In order to update to Drupal 8.5.x from minor version the following procedures must be done:

1. drush pm-uninstall workbench_moderation
2. drush cex
3. drush pm-uninstall lightning_scheduled_updates, entity
4. composer require acquia/lightning:~3.0.0 --no-update
5. composer update acquia/lightning --with-dependencies
6. composer require drupal/scheduled_updates drupal/media_entity_actions
7. composer self-update
8. composer require acquia/lightning:~3.1.1 --no-update
9. composer update
10. composer update
11. drush en media_entity_actions, scheduled_updates
12. drush updb
13. composer require drupal/wbm2cm
14. drush en wbm2cm
15. drush wbm2cm-migrate
16. drush entity-updates