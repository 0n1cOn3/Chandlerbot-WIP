document.addEventListener("DOMContentLoaded", () => {
  const pathname = window.location.pathname;
  let botstop = false;
  const infoarray = [];

  const $ = selector => document.querySelector(selector);
  const $$ = selector => document.querySelectorAll(selector);

  const postData = async (opt, act = "") => {
      const response = await fetch("web.php", {
          method: "POST",
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ opt, act })
      });
      return response.text();
  };

  const getTitle = async () => {
      const data = await postData("title");
      document.title = `${data} - bot`;
  };

  const readLogFile = async () => {
      const container = $(".viewcontainer");
      if (!botstop) {
          const data = await postData("taillog");
          const formatted = data.replace(/ /g, "&nbsp;").replace(/\n/g, "<br />");
          container.innerHTML = formatted;
          container.scrollTop = container.scrollHeight;
      } else {
          container.innerHTML = "<b>bot not running...</b>";
      }
  };

  const stats = async () => {
      $(".logsize").innerHTML = await postData("getlogsize");
      $(".cpuload").innerHTML = await postData("cpuload");
      $(".confmain").innerHTML = await postData("loadconf");
  };

  const queuestats = async () => {
      $(".queue").innerHTML = await postData("queue");
      $(".fwdstatus").innerHTML = await postData("fwdstatus");
  };

  const loadFullLog = async () => {
      const data = await postData("fulllog");
      const formatted = data.replace(/ /g, "&nbsp;").replace(/\n/g, "<br />");
      const fulllog = $(".fulllog");
      fulllog.innerHTML = formatted;
      fulllog.scrollTop = fulllog.scrollHeight;
      $(".fulllogbuttonhide").style.display = "block";
  };

  const botCmdIn = (cmd) => {
      const mainfade = $(".mainfade");
      const botcmdout = $(".botcmdout");
      const botcmdouttext = $(".botcmdouttext");

      if (getComputedStyle(mainfade).opacity == 1) {
          mainfade.style.opacity = 0.05;
          botcmdout.style.opacity = 1;
          $("#botcmdid").disabled = true;
          $("#flog").disabled = true;
      } else {
          mainfade.style.opacity = 1;
          botcmdout.style.display = "none";
          botcmdouttext.style.display = "none";
      }

      botcmdouttext.innerHTML = `
          <b><br>&nbsp;bot action:<center>
          <p style="color:red; display: inline; font-size: 20px;">${cmd}</p></center>
          &nbsp;...just wait</b><br><br>`;
  };

  const botCmd = async (option, cmd) => {
      botCmdIn(cmd);
      await postData(option, cmd);
  };

  const botCmdOkay = () => {
      const mainfade = $(".mainfade");
      const botcmdout = $(".botcmdout");

      if (getComputedStyle(mainfade).opacity == "1") {
          mainfade.style.opacity = 0.05;
          botcmdout.style.opacity = 1;
      } else {
          mainfade.style.opacity = 1;
          botcmdout.style.display = "none";
      }

      $("#botcmdid").disabled = false;
      $("#flog").disabled = false;
  };

  const editChannel = async (cmd) => {
      $(".mainfade").style.opacity = 0.05;
      $("#botcmdid").disabled = true;
      $("#flog").disabled = true;

      $(".editout").style.display = "block";
      $(".editouttext").innerHTML = await postData("getconf", cmd);
  };

  const editDone = () => {
      $(".editout").style.display = "none";
      $(".yesno").style.display = "none";
      $(".mainfade").style.opacity = 1;
      $("#botcmdid").disabled = false;
      $("#flog").disabled = false;
  };

  const yesNo = (info) => {
      const { text, channelInfoKey, to } = info[0];
      const yesno = $(".yesno");
      $(".editout").style.display = "none";
      yesno.innerHTML = `${text}${channelInfoKey}${to}
          <br><br>
          <input class='clickbutton' id='yesnoyes' type='button' value='yes' />
          <input class='clickbutton' id='yesnono' type='button' value='no' />`;
      yesno.style.display = "block";
  };

  const start = () => {
      getTitle();
      $$(".fulllogbuttonhide, .fulllog, .editout, .botcmdout, #botaction, .yesno").forEach(el => el.style.display = "none");
      readLogFile();
      stats();
  };

  // Event delegation
  document.addEventListener("click", e => {
      const id = e.target.id;

      switch (id) {
          case "flog":
              $(".fulllogbutton").style.display = "none";
              $("#botcmdid").style.display = "none";
              $("#botaction").style.display = "none";
              $(".fulllog").innerHTML = "<b>logfile loading...</b>";
              $(".viewcontainer").style.display = "none";
              $(".viewborder").style.display = "none";
              $(".confmain").style.display = "none";
              loadFullLog();
              $(".fulllog").style.display = "block";
              break;

          case "floghide":
              $(".fulllogbutton").style.display = "block";
              $("#botcmdid").style.display = "block";
              $(".fulllogbuttonhide").style.display = "none";
              $(".fulllog").innerHTML = "";
              $(".viewcontainer").style.display = "block";
              $(".viewborder").style.display = "block";
              $(".fulllog").style.display = "none";
              $(".confmain").style.display = "block";
              break;

          case "botcmdid":
              const botaction = $("#botaction");
              botaction.style.display = (botaction.style.display === "none") ? "block" : "none";
              break;

          case "botstart":
              botstop = false;
              botCmd("botcmd", e.target.value);
              $("#botaction").style.display = "none";
              break;

          case "botstop":
              botstop = true;
              botCmd("botcmd", e.target.value);
              $(".viewcontainer").innerHTML = "";
              $("#botaction").style.display = "none";
              break;

          case "botrestart":
              botstop = false;
              botCmd("botcmd", e.target.value);
              $("#botaction").style.display = "none";
              break;

          case "bottest":
              botCmd("test", "test");
              $("#botaction").style.display = "none";
              break;

          case "botokay":
              botCmdOkay();
              break;

          case "editokaybutton":
              editDone();
              break;
      }
  });

  document.addEventListener("keydown", e => {
      if (e.key === "Escape") {
          editDone();
      }
  });

  setInterval(readLogFile, 4000);
  setInterval(stats, 15000);
  setInterval(queuestats, 2000);

  start();
});
