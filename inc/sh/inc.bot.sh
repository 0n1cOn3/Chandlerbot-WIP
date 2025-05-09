function checkpid() {
  if [ -f "${1}" ]; then
    out=$(cat "${1}")
    if ! ps -ef | awk -v pid="${out}" '{if ($2 == pid) {print $2}}' &>/dev/null; then exit 1; fi
  else
    exit 1
  fi
  echo "${out}"
}

function kill_service() {
  if [ "${1}" -ne 0 ]; then
    echo "${2} not running"
  else
    kill "${4}" "${3}" > /dev/null 2>&1
  fi
}

function remove_apache_builtin_modules() {
  ${2} -l | grep -v "Compiled in modules" | sed "s|\.c$|.so|g" | while read -r line; do
    sed -i "/^LoadModule.*\/${line}$/d" "${1}"
  done
}

function get_chandler_bot_conf() {
  out=$(echo "${1}@@${2}" | php -R '$in=explode("@@", $argn); if (array_key_exists($in[1], yaml_parse_file($in[0]))) { echo yaml_parse_file($in[0])[$in[1]];} else exit(1);')
  if [ $? -ne 0 ]; then exit 1; fi
  echo "${out}"
}

function find_bin() {
  local bin_name="$1"
  out=$(command -v "${bin_name}" 2>/dev/null)
  if [ $? -ne 0 ]; then exit 1; fi
  echo "${out}"
}

function apache_mod() {
  out=$(find /usr -name "mod_mpm_event.so" 2>/dev/null | awk -F / '{for (i=1;i<NF;i++) {printf ("%s/",$i)}}')
  if [ -z "${out}" ]; then exit 1; fi
  echo "${out}"
}

function check_service() {
  if ! echo "${2}" | grep -qw "${1}"; then
    echo "${1}" && exit 1
  fi
  echo "${1}"
}

function prepare_web() {
  apache_serverroot="${bot_root}"
  apache_start=$(get_chandler_bot_conf "${chandlerbotconf}" "apache_start")
  if [ "${apache_start}" != "1" ]; then
    echo "ERR: apache_start not configured or not set to '1' @ ${chandlerbotconf}" && exit 1
  fi
  apache_user=$(id -un)
  apache_group=$(id -gn)
  apache_port=$(get_chandler_bot_conf "${chandlerbotconf}" "apache_port")
  if [ "${apache_port}" -lt 1025 ]; then
    echo "ERR: apache_port must be greater than 1024 @ ${chandlerbotconf}" && exit 1
  fi
}

function start_fpm() {
  prepare_web
  php_fpm_bin=$(find_bin "php-fpm")
  cat "${apache_serverroot}/conf/templates/template.php-fpm.conf" \
    | sed "s|@@apache_serverroot@@|${apache_serverroot}|g" \
    | sed "s|@@apache_user@@|${apache_user}|g" \
    | sed "s|@@apache_group@@|${apache_group}|g" \
    | sed "s|@@apache_port@@|${apache_port}|g" > "${apache_serverroot}/conf/php-fpm.conf"

  if ! ${php_fpm_bin} -y "${apache_serverroot}/conf/php-fpm.conf" 2>/dev/null; then
    echo "php-fpm already running..."
    out=$(checkpid "${fpm_pid}")
    kill "${out}" > /dev/null 2>&1
    ${php_fpm_bin} -y "${apache_serverroot}/conf/php-fpm.conf" 2>/dev/null
    echo "php-fpm restarted."
  else
    echo "php-fpm started."
  fi
}

function start_apache() {
  prepare_web
  apachebin=$(find_bin "httpd" || find_bin "apache2")
  apache_modules=$(apache_mod)

  rm -rf "${apache_serverroot}/log/access_log" > /dev/null 2>&1

  apache_pid="${apache_serverroot}/log/tmp/httpd.pid"
  apache_documentroot="${apache_serverroot}/cb-web"

  cat "${apache_serverroot}/conf/templates/template.httpd.conf" \
    | sed "s|@@apache_modules@@|${apache_modules}|g" \
    | sed "s|@@apache_serverroot@@|${apache_serverroot}|g" \
    | sed "s|@@apache_user@@|${apache_user}|g" \
    | sed "s|@@apache_group@@|${apache_group}|g" \
    | sed "s|@@apache_port@@|${apache_port}|g" \
    | sed "s|@@apache_documentroot@@|${apache_documentroot}|g" \
    | sed "s|@@apache_pid@@|${apache_pid}|g" > "${apache_serverroot}/conf/httpd.conf"

  remove_apache_builtin_modules "${apache_serverroot}/conf/httpd.conf" "${apachebin}"

  chmod -R a+rwx "${apache_documentroot}" 2>/dev/null
  chmod -R a+rwx "${apache_serverroot}/log" 2>/dev/null

  ${apachebin} -f "${apache_serverroot}/conf/httpd.conf" -k restart > /dev/null 2>&1
  if [ $? -eq 0 ]; then
    echo "apache (re)started @ port: ${apache_port}"
  else
    echo "ERR: apache not started, something is wrong..."
  fi
}

function start_bot() {
  out=$(checkpid "${bot_pid}")
  if [ -n "${out}" ]; then
    echo "bot already running, exit!" && exit 1
  fi

  cp conf/channels.conf log/conf/${startDate}_channels.conf
  cp conf/chandlerbot.conf log/conf/${startDate}_chandlerbot.conf

  # Log rotation
  find log/*_chandlerbot.app.log -type f | head -n -10 | xargs rm -f
  find log/conf/*chandlerbot.conf -type f | head -n -10 | xargs rm -f
  find log/conf/*channels.conf -type f | head -n -10 | xargs rm -f

  if [ $(id -u) -eq 0 ]; then
    chown -R nobody *
    su nobody -c "nohup bin/chandlerbot.php 2>&1 | tee log.chandlerbot.log | tee log/${startDate}_chandlerbot.app.log > /dev/null &"
  else
    nohup bin/chandlerbot.php 2>&1 | tee log.chandlerbot.log | tee log/${startDate}_chandlerbot.app.log > /dev/null &
  fi
}

function start_service() {
  echo "start ${1}"
  case "${1}" in
    "bot") start_bot ;;
    "apache") start_apache; sleep 2; status_service "apache" ;;
    "fpm") start_fpm ;;
    "web") start_service "apache"; start_service "fpm" ;;
    "all") start_service "bot"; start_service "web" ;;
  esac
}

function stop_service() {
  echo "stop ${1}"
  case "${1}" in
    "bot") out=$(checkpid "${bot_pid}"); kill_service "$?" "${1}" "${out}" "-9" ;;
    "apache") out=$(checkpid "${apache_pid}"); kill_service "$?" "${1}" "${out}" "" ;;
    "fpm") out=$(checkpid "${fpm_pid}"); kill_service "$?" "${1}" "${out}" "" ;;
    "web") stop_service "apache"; stop_service "fpm" ;;
    "all") stop_service "web"; stop_service "bot"; stop_service "madeline" ;;
  esac
}

function status_service() {
  case "${1}" in
    "bot" | "fpm")
      out=$(checkpid "${bot_pid}")
      echo "${1} $( [ $? -eq 0 ] && echo "running" || echo "not running" )"
      ;;
    "apache")
      out=$(checkpid "${apache_pid}")
      if [ $? -eq 0 ]; then
        apache_ip_running=$(ip -br address | grep -iwv lo | awk '{print $3}' | awk -F / '{print $1}')
        apache_port=$(get_chandler_bot_conf "${chandlerbotconf}" "apache_port")
        echo "${1} running on: http://${apache_ip_running}:${apache_port}/"
      else
        echo "${1} not running"
      fi
      ;;
    "web") echo "status ${1}"; status_service "apache"; status_service "fpm" ;;
    "all") echo "status ${1}"; status_service "web"; status_service "bot"; status_service "madeline" ;;
  esac
}

function bot_sh_usage() {
  echo "usage: 
  ./bot.sh start
  ./bot.sh stop
  ./bot.sh restart
  ./bot.sh status
  -
  ./bot.sh setuptg

  ./bot.sh start -s [bot|web]
  ./bot.sh stop -s [bot|web]
  ./bot.sh restart -s [bot|web]
  ./bot.sh status -s [bot|web]
  eg. ./bot.sh restart -s bot
  ./bot.sh restart -s web
  "
}
