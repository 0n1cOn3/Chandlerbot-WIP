#!/bin/bash

# Get the current timestamp
startDate=$(date "+%Y%m%d%H%M%S")

# Source the bot script
source inc/sh/inc.bot.sh

# Define bot paths and configurations
bot_root=$(pwd)
bot_pid="${bot_root}/log/tmp/pandabot.pid"
madeline_session="${bot_root}/session.madeline"
apache_pid="${bot_root}/log/tmp/httpd.pid"
fpm_pid="${bot_root}/log/tmp/php-fpm.pid"

pandabotconf="${bot_root}/conf/pandabot.conf"
services="bot|web|apache|fpm|madeline|"

# Get the wait timer from the config file
waittimer=$(getpandabotconf "${pandabotconf}" "botsh_restart_wait_timer")
if [ $? -ne 0 ]; then
  echo "ERR: botsh_restart_wait_timer not configured in ${pandabotconf}"
  exit 1
fi

# Check for arguments
if [ $# -eq 0 ]; then
  bot_sh_usage
  exit 1
fi

# Process command-line options
OPTIND=2
while getopts "s:" opt; do
  case "${opt}" in
    s) service=${OPTARG} ;;
    *) bot_sh_usage; exit 1 ;;
  esac
done

# Validate service if provided
if [ -n "${service}" ]; then
  out=$(checkservice "${service}" "${services}")
  if [ $? -ne 0 ]; then
    echo "service: ${out} not found"
    bot_sh_usage
    exit 1
  fi
else
  service="all"
fi

# Function to display service status with color-coded symbols
show_status() {
  local service_name="$1"
  local status="$2"
  local symbol=""
  local color=""

  if [ "${status}" == "up" ]; then
    symbol="✔"
    color="\e[32m" # Green
  else
    symbol="✘"
    color="\e[31m" # Red
  fi

  echo -e "${color}${service_name}: ${symbol}\e[0m"
}

# Function to start a service with error handling
start_service() {
  local service="$1"
  echo "Starting service: ${service}"
  if ! systemctl start "${service}"; then
    echo "Error: Failed to start service: ${service}"
    exit 1
  fi
}

# Function to stop a service with error handling
stop_service() {
  local service="$1"
  echo "Stopping service: ${service}"
  if ! systemctl stop "${service}"; then
    echo "Error: Failed to stop service: ${service}"
    exit 1
  fi
}

# Function to check the status of a service with error handling
status_service() {
  local service="$1"
  echo "Checking status of service: ${service}"
  if ! systemctl is-active --quiet "${service}"; then
    echo "Service ${service} is not running"
    return 1
  else
    echo "Service ${service} is running"
    return 0
  fi
}

# Handle different actions based on the first argument
case "$1" in
  "start")
    start_service "${service}"
    ;;
  "stop")
    stop_service "${service}"
    ;;
  "restart")
    stop_service "${service}"
    echo "Waiting ${waittimer} seconds before restarting..."
    sleep "${waittimer}"
    start_service "${service}"
    ;;
  "setuptg")
    echo "Setting up Telegram for service: ${service}"
    bin/setuptg.php
    ;;
  "status")
    # Check status for each service
    if [ "${service}" == "all" ]; then
      for s in ${services}; do
        status=$(status_service "${s}")
        show_status "${s}" "${status}"
      done
    else
      status=$(status_service "${service}")
      show_status "${service}" "${status}"
    fi
    ;;
  *)
    bot_sh_usage
    ;;
esac
