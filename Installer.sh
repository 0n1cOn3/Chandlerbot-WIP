#!/bin/bash

# Function to check if the script is running as root
check_root() {
  if [ "$(id -u)" -ne 0 ]; then
    echo "ERR: must be run as: root"
    exit 1
  fi
}

# Function to determine the distribution
detect_os() {
  if [ -f /etc/centos-release ]; then
    DISTRO="centos"
  elif [ -f /etc/fedora-release ]; then
    DISTRO="fedora"
  elif [ -f /etc/debian_version ]; then
    DISTRO="debian"
  elif [ -f /etc/lsb-release ]; then
    DISTRO="ubuntu"
  else
    echo "ERR: Unsupported distribution"
    exit 1
  fi
}

# Function to install necessary repositories and PHP modules for Fedora
install_fedora_php_modules() {
  echo "Installing EPEL repository..."
  sudo yum install -y epel-release
  echo "Installing Remi repository..."
  dnf install https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %{rhel}.rpm -y
  sudo yum-config-manager --enable remi-php83

  echo "Installing essential Packages..."
  dnf install net-tools git postgresql postgresql-server httpd php-fpm compose python3 python3-pip -y 
  echo "Enabling PHP 8.3 module..."
  dnf module install php:remi-8.3 -y
  dnf install php-pecl-yaml php-pgsql php-pdo php-cli php-mbstring php-curl php-sockets php-xml php-zip php-gmp -y
  sudo phpenmod mbstring curl sockets xml zip gmp
  echo "Restarting web/PHP-FPM services if applicable..."
  sudo systemctl restart php-fpm || true
}

# Function to install necessary repositories and PHP modules for Debian/Ubuntu
install_debian_ubuntu_php_modules() {
  echo "Updating package list..."
  apt update -y

  echo "Installing essential PHP repositories..."
  apt install -y software-properties-common net-tools git
  add-apt-repository ppa:ondrej/php -y

  echo "Installing PHP 8.3 and necessary modules..."
  apt install -y php8.3 php8.3-fpm php8.3-pgsql php8.3-pear php8.3-cli php8.3-curl php8.3-mbstring php8.3-sockets php8.3-xml php8.3-gmp

  echo "Installing PostgreSQL and other essential packages..."
  apt install -y postgresql postgresql-contrib apache2
  sudo phpenmod mbstring curl sockets xml zip gmp
  echo "Restarting web/PHP-FPM services if applicable..."
  sudo systemctl restart php-fpm || true
}

# Function to configure PostgreSQL
configure_postgresql() {
  echo "Initializing PostgreSQL database..."
  /usr/bin/postgresql-setup --initdb
  cd /var/lib/pgsql/data

  echo "Configuring PostgreSQL access..."
  sed 's|host    all             all             127.0.0.1/32            ident|host    all             all             0.0.0.0/0            trust|g' pg_hba.conf > pg_hba.conf1
  mv pg_hba.conf1 pg_hba.conf
  
  echo "Restarting PostgreSQL..."
  systemctl restart postgresql
}

# Function to create the database
create_database() {
  echo "Creating database 'chandlerbot'..."
  su - postgres -c 'echo "CREATE DATABASE chandlerbot;" | psql'
}

# Function to display configuration instructions
show_instructions() {
  local db_host
  db_host=$(ip -br address | grep -iwv lo | awk '{print $3}' | awk -F / '{print $1}')

  printf "\n------------------------- --- -- -  -\n"
  echo "Edit conf/chandlerbot.conf and conf/channels.conf:"
  echo "dbname: chandlerbot"
  echo "dbhost: ${db_host}"
  echo "dbport: 5432"
  echo "dbuser: postgres"
  echo "dbpass: false"
  printf "\n"
  echo "Setup Telegram session with ./bot.sh setuptg"
  echo "Start bot with ./bot.sh start"
  echo "Stop bot with ./bot.sh stop"
  echo "------------------------- --- -- -  -"
}

# Main script execution
check_root
detect_os

case $DISTRO in
  centos)
    check_centos
    install_php_modules
    ;;
  fedora)
    install_fedora_php_modules
    ;;
  debian|ubuntu)
    install_debian_ubuntu_php_modules
    ;;
  *)
    echo "ERR: Unsupported distribution"
    exit 1
    ;;
esac

configure_postgresql
create_database
show_instructions
