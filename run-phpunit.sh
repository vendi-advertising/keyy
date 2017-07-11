#!/bin/bash

##see: http://stackoverflow.com/questions/192249/how-do-i-parse-command-line-arguments-in-bash
# Use -gt 1 to consume two arguments per pass in the loop
# Use -gt 0 to consume one or more arguments per pass in the loop

DB_USER='unit_chris'
DB_PASS='unit_chris'
DB_NAME='unit_chris'
while [[ $# -gt 0 ]]
do
    key="$1"
    echo "$key";

        case $key in

            -g|--group)
                GROUP="$2"
                shift # past argument
            ;;

            -d|--database)
                DB_NAME="$2"
                shift # past argument
            ;;

            -u|--user)
                DB_USER="$2"
                shift # past argument
            ;;

            -p|--password)
                DB_PASS="$2"
                shift # past argument
            ;;

            --update-composer)
                UPDATE=true
            ;;

            *)
                    # unknown option
            ;;
        esac

    shift # past argument or value
done

if [ ! -d './vendor' ]; then
    UPDATE=true
fi

if [ "$UPDATE" = true ]; then
    composer update
    composer install
fi

CONFIG_TEMPLATE_FILE='./vendor/WordPress/wordpress-develop/wp-tests-config-sample.php';
CONFIG_FILE='./vendor/WordPress/wordpress-develop/wp-tests-config.php';

cp $CONFIG_TEMPLATE_FILE $CONFIG_FILE
sed -i "s|youremptytestdbnamehere|${DB_NAME}|g" $CONFIG_FILE
sed -i "s|yourusernamehere|${DB_USER}|g" $CONFIG_FILE
sed -i "s|yourpasswordhere|${DB_PASS}|g" $CONFIG_FILE

if [ -z "$GROUP" ]; then
    ./vendor/bin/phpunit --coverage-html ./tests/logs/coverage/
else
    ./vendor/bin/phpunit --coverage-html ./tests/logs/coverage/ --group $GROUP
fi


#phpunit --coverage-html ./tests/logs/coverage/

