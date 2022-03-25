#!/usr/bin/env bash

# Functions here:
function w() {
  mysql -u root -proot -e "create database sniccowp_1";
  cd /tmp || exit 1
  mkdir /tmp/wordpress
  cd /tmp/wordpress || exit 1
  wp core download --force --skip-content --version=$1
  wp config create --dbname="sniccowp_1" --dbuser="root" --dbpass="root" --dbhost="127.0.0.1" --dbprefix="wp_"
  wp core install --url="sniccowp.test" --title="SniccoWP" --admin_user="admin" --admin_password="admin" --admin_email="admin@sniccowp.com" --skip-email
  wp core update-db
  wp rewrite structure '/%postname%/'
  wp plugin install redis-cache
  wp plugin activate redis-cache
  wp redis enable
}

# Checking requirements now
printf "Checking requirements now!\n\n"
# Check for composer
if ! command -v composer &> /dev/null
then
    printf "composer could not be found\n"
    printf "Make sure composer is globally available on your machine: https://getcomposer.org/doc/00-intro.md\n"
    exit 1
else
    printf "composer found\n"
fi

# Check for npm
if ! command -v npm &> /dev/null
then
    printf "npm could not be found\n"
    printf "Make sure npm is globally available on your machine: https://docs.npmjs.com/downloading-and-installing-node-js-and-npm\n"
    exit 1
else
    printf "npm found\n\n"
fi

printf "running: composer install --no-interaction\n\n"
composer install --no-interaction

printf "running: npm install\n\n"
npm install

# Check for -w (=> with WordPress option)
while getopts w: opt
  do
    case $opt in
      w)
        printf "Installing WordPress\n\n"
        w $OPTARG
        ;;
      *)
        exit 1
        ;;
    esac
  done