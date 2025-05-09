#!/bin/bash

# Constants
GREEN="\e[32m"
RED="\e[31m"
RESET="\e[0m"
CHECK_MARK="✔"
CROSS_MARK="✘"

# File paths
BOT_ROOT=$(pwd)
PID_DIR="${BOT_ROOT}/log/tmp"
CHANDLERBOT_PID="${PID_DIR}/chandlerbot.pid"
CHANDLERBOT_CONF="${BOT_ROOT}/conf/chandlerbot.conf"
SERVICES="bot|web|apache|fpm|madeline|"

# Utility function for logging
log_error() {
  echo -e "${RED}Error: $1${RESET}"
}

log_info() {
  echo -e "${GREEN}Info: $1${RESET}"
}

# Function to display usage
bot_sh_usage() {
  echo "Usage: $0 [start|stop|restart|setuptg|status]"
  exit 1
}

# Function to get configuration values
get_config_value() {
  local config_file="$1"
  local key="$2"
  local value
  value=$(getchandlerbotconf "${config_file}" "${key}")
  if [ $? -ne 0 ]; then
    log_error "${key} not configured in ${config_file}"
    exit 1
  fi
  echo "${value}"
}

# Function to manage services with systemctl
manage_service() {
  local service="$1"
  local action="$2"

  echo "${action^} service: ${service}"  # Capitalize action
  if ! systemctl "${action}" "${service}"; then
    log_error "Failed to ${action} service: ${service}"
    exit 1
  fi
}

# Function to check the status of a service
status_service() {
  local service="$1"
  if systemctl is-active --quiet "${service}"; then
    echo "${CHECK_MARK}"
    return 0
  else
    echo "${CROSS_MARK}"
    return 1
  fi
}

# Function to display service status
show_status() {
  local service_name="$1"
  local status="$2"
  local symbol=""
  local color=""

  if [ "${status}" == "${CHECK_MARK}" ]; then
    symbol="${CHECK_MARK}"
    color="${GREEN}"
  else
    symbol="${CROSS_MARK}"
    color="${RED}"
  fi

  echo -e "${color}${service_name}: ${symbol}${RESET}"
}

# Main logic
main() {
  # Check for arguments
  if [ $# -lt 1 ]; then
    bot_sh_usage
  fi

  # Assign variables
  local action="$1"
  shift
  local service="all"

  while getopts "s:" opt; do
    case "${opt}" in
      s) service="${OPTARG}" ;;
      *) bot_sh_usage ;;
    esac
  done

  # Validate service
  if [ "${service}" != "all" ]; then
    if ! checkservice "${service}" "${SERVICES}"; then
      log_error "Service ${service} not found"
      bot_sh_usage
    fi
  fi

  # Perform actions
  case "${action}" in
    "start")
      manage_service "${service}" "start"
      ;;
    "stop")
      manage_service "${service}" "stop"
      ;;
    "restart")
      manage_service "${service}" "stop"
      local waittimer
      waittimer=$(get_config_value "${CHANDLERBOT_CONF}" "botsh_restart_wait_timer")
      log_info "Waiting ${waittimer} seconds before restarting..."
      sleep "${waittimer}"
      manage_service "${service}" "start"
      ;;
    "setuptg")
      log_info "Setting up Telegram for service: ${service}"
      bin/setuptg.php
      ;;
    "status")
      if [ "${service}" == "all" ]; then
        for s in ${SERVICES}; do
          local status
          status=$(status_service "${s}")
          show_status "${s}" "${status}"
        done
      else
        local status
        status=$(status_service "${service}")
        show_status "${service}" "${status}"
      fi
      ;;
    *)
      bot_sh_usage
      ;;
  esac
}

# Run the main function
main "$@"
