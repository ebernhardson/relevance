#!/bin/bash

if [ ! -f /usr/bin/mysql ]; then
	export DEBIAN_FRONTEND=noninteractive
	debconf-set-selections <<< "mariadb-server-5.5 mysql-server/root_password password root"
	debconf-set-selections <<< "mariadb-server-5.5 mysql-server/root_password_again password root"
	
	apt-get update
	apt-get -y install apache2 libapache2-mod-php5 mariadb-server-5.5 php5-curl php5-mysql php5-cli
fi

# apache2 adds this useless dir
if [ -d /var/www/html ]; then
	rm -rf /var/www/html
fi

# Setup the default app.ini if not already configured
if [ ! -f /var/www/app.ini ]; then
	cp /var/www/app.vagrant.ini /var/www/app.ini
fi

# Initialize the database
echo "CREATE DATABASE IF NOT EXISTS relevance" | mysql -u root -proot 
mysql -u root -proot relevance < /var/www/schema.mysql.sql

# Setup the apache2 configuration
cat > /etc/apache2/sites-available/000-default.conf <<EOD
<VirtualHost *:80>
	ServerAdmin webmaster@localhost
	DocumentRoot /var/www/public

	ErrorLog \${APACHE_LOG_DIR}/error.log
	CustomLog \${APACHE_LOG_DIR}/access.log combined

	RewriteEngine on
	RewriteCond /var/www/public/%{REQUEST_FILENAME} !-f
	RewriteCond /var/www/public/%{REQUEST_FILENAME} !-d
	RewriteRule ^(.*)$ /index.php/$1 [L]
</VirtualHost>
EOD
a2enmod rewrite
service apache2 reload

# The JWT lib really doesn't like the clock being off, even by 15s or so,
# So lets make sure it's reasonable
ntpdate-debian

# setup composer
curl https://getcomposer.org/download/1.0.0-beta1/composer.phar > /usr/local/bin/composer
if ! echo "4344038a546bd0e9e2c4fa53bced1c7faef1bcccab09b2276ddd5cc01e4e022a  /usr/local/bin/composer" | sha256sum -c; then
	rm /usr/local/bin/composer
	exit 1
fi
chmod +x /usr/local/bin/composer

cd /var/www
/usr/local/bin/composer install

# Make sure apache can write to the cache directory
# For a prod deployment it is better to pre-build the twig's
# and not have anything writable by www-data
sudo chown -R www-data /var/www/cache/
