define create_db
	@mysql --execute="CREATE USER IF NOT EXISTS 'forge'@'localhost';"
	@mysql --execute="CREATE DATABASE IF NOT EXISTS $1;"
	@mysql --execute="GRANT ALL PRIVILEGES ON *.* TO 'forge'@'localhost';"
endef

setup:
	composer install
	make setup_create_db
	make setup_run_migration

setup_create_db:
	$(call create_db,laravel_car_interview)

setup_run_migration:
	php artisan migrate
