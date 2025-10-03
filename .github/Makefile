# Install N98-Magerun
n98-magerun2.phar:
	wget -q https://files.magerun.net/n98-magerun2.phar
	chmod +x ./n98-magerun2.phar

# Check Magento installation
sys-check: n98-magerun2.phar
	./n98-magerun2.phar sys:check

# Install Magento (without starting Apache)
magento:
	sed '/exec /d' /tmp/install_magento.sh | bash

# Plugin install
install:
	composer config --json repositories.local '{"type": "path", "url": "/data/extensions/workdir", "options": { "symlink": false } }'
	composer require "adyen/adyen-magento2-expresscheckout:*"
	bin/magento module:enable --all
	bin/magento setup:upgrade
	bin/magento setup:di:compile

# Configuration
configure: n98-magerun2.phar
	bin/magento config:set payment/adyen_abstract/demo_mode 1
	bin/magento adyen:enablepaymentmethods:run
	bin/magento config:set payment/adyen_abstract/merchant_account "${ADYEN_MERCHANT}"
	bin/magento config:set payment/adyen_abstract/client_key_test "${ADYEN_CLIENT_KEY}"
	bin/magento config:set payment/adyen_abstract/payment_methods_active 1
	bin/magento config:set payment/adyen_googlepay/express_show_on "1,2,3"
	bin/magento config:set payment/adyen_applepay/express_show_on "1,2,3"
	bin/magento config:set payment/adyen_paypal_express/express_show_on "1,2,3"
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/notification_password '1234'  > /dev/null
	./n98-magerun2.phar config:store:set --encrypt payment/adyen_abstract/api_key_test "${ADYEN_API_KEY}" > /dev/null

# Clear cache
flush:
	bin/magento cache:flush

# Full plugin setup
plugin: install configure flush

# Production mode
production:
	bin/magento deploy:mode:set production

# Setup permissions
fs:
	find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
	find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +
	chmod 777 -R var
	chown -R www-data:www-data .
	chmod u+x bin/magento
	echo "memory_limit = -1" > /usr/local/etc/php/conf.d/memory.ini

# GraphQL tests
MAGENTO_ROOT=/var/www/html
GRAPHQL_XML_DIST=${MAGENTO_ROOT}/vendor/adyen/adyen-magento2-expresscheckout/Test/phpunit_graphql.xml.dist
GRAPHQL_XML=${MAGENTO_ROOT}/dev/tests/api-functional/phpunit_graphql.xml

setup-graphql:
	sed '/TESTS_BASE_URL/s/magento.url/${MAGENTO_HOST}/g' ${GRAPHQL_XML_DIST} >> ${GRAPHQL_XML}
	bin/magento indexer:set-mode realtime
	bin/magento cache:clean
	bin/magento indexer:reindex

graphql:
	@cd ${MAGENTO_ROOT}/dev/tests/api-functional && ${MAGENTO_ROOT}/vendor/bin/phpunit -c ${GRAPHQL_XML}

phpstan-run:
	vendor/bin/phpstan analyse -c vendor/adyen/adyen-magento2-expresscheckout/phpstan.neon

phpunit-run:
	cp vendor/adyen/adyen-magento2-expresscheckout/Test/phpunit.xml.dist dev/tests/unit/phpunit.xml
	cd dev/tests/unit; \
		../../../vendor/bin/phpunit \
		--coverage-clover=../../../build/clover.xml \
		--log-junit=../../../build/tests-log.xml \
		-c phpunit.xml \
		../../../vendor/adyen/adyen-magento2-expresscheckout/Test/Unit

codesniffer-run:
	vendor/bin/phpcs --standard=Magento2 \
		--extensions=php,phtml \
		--error-severity=10 \
		--warning-severity=0 \
		--ignore-annotations \
		-p vendor/adyen/adyen-magento2-expresscheckout
