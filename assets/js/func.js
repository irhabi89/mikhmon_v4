localStorage.setItem("?admin_idleto", 15);
var idleto = localStorage.getItem(session + "_idleto");
function idleTimer() {
  if (document.getElementById("timer")) {
    var e = document.getElementById("timer");
    function t() {
      e.innerHTML = idleto + ":00";
    }
    (window.onmousemove = t),
      (window.onmousedown = t),
      (window.onclick = t),
      (window.onscroll = t),
      (window.onkeypress = t);
  }
}
if (document.getElementById("timer")) {
  idleto = localStorage.getItem(session + "_idleto");
  document.getElementById("timer").innerHTML = idleto + ":00";
}
function startTimer() {
  if (document.getElementById("timer")) {
    var e = document.getElementById("timer"),
      t = e.innerHTML.split(/[:]+/),
      a = t[0],
      s = checkSecond(t[1] - 1);
    59 == s && (a -= 1),
      0 == a && 0 == s && ((e.innerHTML = "0:00"), $("#logOut").click()),
      (e.innerHTML = a + ":" + s),
      setTimeout(startTimer, 1e3);
  }
}
function checkSecond(e) {
  return e < 10 && e >= 0 && (e = "0" + e), e < 0 && (e = "59"), e;
}
function countTableRow(e) {
  var t = $("#" + e + " tr").filter(function () {
    return $(this).hasClass("yes");
  });
  $("#total-" + e).html(t.length);
}
function menuNonActive(e) {
  $("#" + e)
    .removeClass("block")
    .addClass("hide"),
    $("#btn-" + e).removeClass("card-tab-active");
}
function menuActive(e) {
  $("#" + e)
    .removeClass("hide")
    .addClass("block"),
    $("#btn-" + e).addClass("card-tab-active");
}
function filterBy(e, t, a) {
  var s = document.getElementById(t);
  $("#" + t).val(a),
    filterTable(e, t),
    "" == s.value
      ? localStorage.setItem(session + "AutoReload", 0)
      : localStorage.setItem(session + "AutoReload", 1),
    s.dispatchEvent(new KeyboardEvent("keyup", { key: "Shift" }));
}
function shPass(e) {
  var t = $("#" + e);
  t.hasClass("key") ? t.removeClass("key") : t.addClass("key");
}
function filterTable(e, t, a) {
  var s = document.getElementById(t);
  $("#" + t).val(a),
    setClassYN(e, t),
    s.dispatchEvent(new KeyboardEvent("keyup", { key: "Shift" }));
}
function countReport(e, t) {
  (getprice = 0),
    $("#" + e + " tr")
      .filter(function () {
        return $(this).hasClass("yes");
      })
      .each(function () {
        getprice += Number($(this).find("span").html());
      }),
    $("#" + t).html(currencyFormat(getprice, currency));
}
function countReportD(e, t) {
  (getprice = 0),
    (d = t.substring(5, 3)),
    (m = t.substring(0, 3)),
    $("#" + e + " tr")
      .filter(function () {
        return $(this).hasClass(t);
      })
      .each(function () {
        getprice += Number($(this).find("span").html());
      });
  var a = "['<b>" + d + " " + m + "</b>'," + getprice + "],";
  localStorage.getItem(session + "_resume_report")
    ? localStorage.setItem(
        session + "_resume_report",
        localStorage.getItem(session + "_resume_report") + a
      )
    : localStorage.setItem(session + "_resume_report", a);
}
function countReportT(e, a) {
  (getprice = 0), (d = a.substring(5, 3)), (m = a.substring(0, 3));
  var s = $("#" + e + " tr").filter(function () {
    return $(this).hasClass(a);
  });
  s.each(function () {
    getprice += Number($(this).find("span").html());
  }),
    (t = a.substring(0, 3) + "/" + a.substring(5, 3) + "/" + a.substring(9, 5)),
    (tm = a.substring(0, 3) + " " + a.substring(9, 5)),
    $("#count-report-t").html(currencyFormat(getprice, currency)),
    $("#count-vcr-t").html(s.length),
    $("#filterby-t").html(t.capitalize()),
    $("#filterby").html(tm.capitalize());
}
function filterTableReport(e, t, a, s) {
  var o = new Date(),
    n = (Number(o.getMonth()), document.getElementById(t));
  $("#" + t).val(a),
    setClassYN(e, t),
    n.dispatchEvent(new KeyboardEvent("keyup", { key: "Shift" })),
    countReport(e, s),
    $("#count-vcr").html($("#total-report").html()),
    (tm =
      $("#filterby-t").html().split("/")[0] +
      " " +
      $("#filterby-t").html().split("/")[2]),
    "" == a
      ? ($("#filterby").html(tm.capitalize()), $("#ld-report").fadeIn(200))
      : ($("#filterby").html(a), $("#ld-report").fadeOut(200));
}
function randomN(e, t) {
  var a = e + Math.random() * (t - e);
  return Math.round(a);
}
function updateTable(e) {
  document
    .getElementById(e)
    .dispatchEvent(new KeyboardEvent("keyup", { key: "Shift" }));
}
function selectFirst(e) {
  var t;
  for (idx = e.split(","), t = 0; t < idx.length; t++)
    $("#" + idx[t]).val($("#" + idx[t] + " option:first").val());
}
function setClassYN(e, t) {
  (filter = new RegExp(String($("#" + t).val()), "i")),
    $("#" + e + " tr").filter(function () {
      $(this).each(function () {
        (found = !1),
          $(this)
            .children()
            .each(function () {
              (content = $(this).html()), content.match(filter) && (found = !0);
            }),
          found
            ? $(this).removeClass("no").addClass("yes")
            : $(this).removeClass("yes").addClass("no");
      });
    }),
    countTableRow(e);
}
function printVcr(e) {
  "vc" == e.split("-")[0] || "up" == e.split("-")[0]
    ? ($("#printVcr").show(),
      $("#printVcrS").show(),
      $("#printVcr").attr("onclick", 'pVcr("' + e + '","d")'),
      $("#printVcrS").attr("onclick", 'pVcr("' + e + '","s")'))
    : ($("#printVcr").hide(), $("#printVcrS").hide());
}
function pVcr(e, t) {
  window.open(session + "/print&" + t + "&c=" + e, "_blank");
}
function setC(e) {
  ("vc" != e.split("-")[0] && "up" != e.split("-")[0]) ||
    ($("#select-comment").val(e),
    filterTable("users", "searchUsers", e),
    printVcr(e));
}
function setTheme(e) {
  var t = localStorage.getItem(session + "_theme");
  e != t &&
    ($.get({ url: session + "/set_theme&theme=" + e })
      .fail(function () {
        setTheme(e);
      })
      .always(function () {
        window.location.href = location.href.replace(/#/gi, "");
      }),
    localStorage.setItem(session + "_theme", e));
}
"disable" === idleto || "" === idleto
  ? document.getElementById("timer") &&
    (document.getElementById("timer").innerHTML = "00:00")
  : (idleTimer(), startTimer()),
  $("#iface-name").change(function () {
    var e = $("#iface-name").val();
    localStorage.setItem(session + "_iface", e),
      clearInterval(window.ifaceI),
      clearInterval(window.ifaceII),
      $.getJSON(session + "/get_traffic/&iface=" + e, function (t) {
        try {
          (txrx =
            '[{"name":"Tx","data":["' +
            t.tx +
            '"]},{"name":"Rx","data":["' +
            t.rx +
            '"]}]'),
            localStorage.setItem(session + "_traffic_data", txrx),
            $("#applog").append(
              "<tr><td>" +
                timeStamp() +
                "</td><td>Loading Traffic " +
                e +
                "</td></tr>"
            ),
            scrollD();
        } catch (e) {
          console.log("get_traffic"),
            $("#applog").append(
              "<tr><td>" +
                timeStamp() +
                '</td><td style="color:#f86c6b;">get_traffic : timeout</td></tr>'
            ),
            scrollD();
        }
      }).always(function () {
        trafficMonitor(localStorage.getItem(session + "_theme"));
      });
  });
var jesD = {
  dec: function (e, t = 25) {
    var a = "";
    e.toString();
    for (var s = 0; s < e.length; s++) {
      var o = e.charCodeAt(s) ^ t;
      a += String.fromCharCode(o);
    }
    return a;
  }
};
function scrollD() {
  var e = $("#applogd");
  $("#applog tr").length > 31 &&
    $("#applog").html(
      "<tr><td>" + timeStamp() + "</td><td>Loading data...</td></tr>"
    );
  var t = e.scrollTop();
  e.scrollTop(t + 100);
}
function timeStamp() {
  var e = "",
    t = new Date(),
    a = t.getHours(),
    s = t.getMinutes(),
    o = t.getSeconds();
  return (
    s < 10 && (s = "0" + s),
    o < 10 && (o = "0" + o),
    (e += a + ":" + s + ":" + o)
  );
}
function makeid(e) {
  for (
    var t = "",
      a = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",
      s = a.length,
      o = 0;
    o < e;
    o++
  )
    t += a.charAt(Math.floor(Math.random() * s));
  return t;
}
function diffct(e) {
  (cct = timeStamp()), Number(e.split(":")[1]), Number(cct.split(":")[1]);
  var t = 60 * +Number(e.split(":")[0]) + +Number(e.split(":")[1]),
    a = 60 * +Number(cct.split(":")[0]) + +Number(cct.split(":")[1]);
  return a - t;
}
function detHA(e) {
  "none" == document.getElementById(e).style.display
    ? ($("#" + e).fadeIn(100),
      $("#t" + e).html('<i  class="fa fa-caret-up"></i> less'))
    : ($("#" + e).fadeOut(100),
      $("#t" + e).html('<i  class="fa fa-caret-down"></i> more'));
}
function connect(e, t) {
  isMobile()
    ? $.notify("Connecting...", "info")
    : $("#" + e).notify("Connecting...", "info"),
    (url = "./?" + t + "/connect"),
    $.get(url, function (a) {
      var s = a.split(",")[0];
      "Connected" === s
        ? (console.log(s), localStorage.setItem(t, s))
        : "Invalid username or password" === s
        ? (isMobile() ? $.notify(s, "error") : $("#" + e).notify(s, "error"),
          localStorage.setItem(t, s),
          console.log(s))
        : "Error" === s &&
          (console.log("Could not connect to MikroTik"),
          localStorage.setItem(t, "Could not connect to MikroTik"));
    }).always(function () {
      var a = localStorage.getItem(t);
      "Connected" === a
        ? (location.href = "./?" + t)
        : "Could not connect to MikroTik" === a
        ? (isMobile() ? $.notify(a, "error") : $("#" + e).notify(a, "error"),
          setsess())
        : setsess(),
        localStorage.setItem(t, "");
    });
}
function setsess() {
  $("#lsession") &&
    setTimeout(function () {
      $("#lsession").val(session.split("?")[1]);
    }, 5e3);
}
function daysInMonth(e, t) {
  return new Date(t, e, 0).getDate();
}
String.prototype.capitalize = function () {
  return this.charAt(0).toUpperCase() + this.slice(1);
};
var yy = yyyy,
  tmm = mm + 1;
function setYear(e) {
  for (i = yy; i >= 2018; i--)
    $("#" + e).append('<option value="' + i + '">' + i + "</option>");
}
function setMonth(e) {
  var t = [
    "",
    "jan",
    "feb",
    "mar",
    "apr",
    "may",
    "jun",
    "jul",
    "aug",
    "sep",
    "oct",
    "nov",
    "dec"
  ];
  for (i = 1; i < 13; i++)
    mm + 1 == i
      ? $("#" + e).append(
          '<option value="' +
            t[i] +
            '" selected>' +
            t[i].capitalize() +
            "</option>"
        )
      : $("#" + e).append(
          '<option value="' + t[i] + '">' + t[i].capitalize() + "</option>"
        );
  $("#btnFilter").attr(
    "title",
    "Filter " + $("#month").val().capitalize() + " " + $("#year").val()
  );
}
function setDay(e, t = tmm, a = yy) {
  var s = daysInMonth(t, a);
  for (
    $("#" + e).append('<option value="day">Day</option>'), i = 1;
    i < s + 1;
    i++
  )
    i < 10 && (i = "0" + i),
      $("#" + e).append('<option value="' + i + '">' + i + "</option>");
}
function filterMonth(e, t, a) {
  var s = $("#" + t).val();
  console.log(s);
  var o = [
      "",
      "jan",
      "feb",
      "mar",
      "apr",
      "may",
      "jun",
      "jul",
      "aug",
      "sep",
      "oct",
      "nov",
      "dec"
    ].indexOf(s),
    n = $("#" + a).val();
  $("#" + e).html(""),
    setDay(e, o, n),
    filterTableReport("report", "searchReport", "", "count-report"),
    loadSReport(Number(o) + "/" + n);
}
function filterDay(e, t, a) {
  (t = $("#" + t).val()), (a = $("#" + a).val());
  filterTableReport(
    "report",
    "searchReport",
    "day" !== e ? t + "/" + e + "/" + a : "",
    "count-report"
  );
}
function selectFO(e) {
  selectTags = $("#" + e);
  for (var t = 0; t < selectTags.length; t++) selectTags[t].selectedIndex = 0;
}
function setfilterProfile() {
  $(".hotspot-users").fancyTable({
    inputId: "searchUsers",
    sortColumn: 2,
    pagination: !0,
    perPage: 15,
    globalSearch: !0,
    globalSearchExcludeColumns: [2, 3, 8]
  });
}
function downloadCSV(e, t) {
  var a, s;
  (a = new Blob([e], { type: "text/csv" })),
    ((s = document.createElement("a")).download = t),
    (s.href = window.URL.createObjectURL(a)),
    (s.style.display = "none"),
    document.body.appendChild(s),
    s.click();
}
function exportToCSV(e, t) {
  var a = [],
    s = $("#" + t + " tr").filter(function () {
      return $(this).hasClass("yes");
    });
  a.push($("title").html().toUpperCase() + ",,,,,,,,"),
    a.push(e.split(".")[0].replace(/_|\?/gi, " ").toUpperCase() + ",,,,,,,,"),
    a.push(",,,,,,,,"),
    a.push(",,,,,,Qty," + (s.length - 1) + ","),
    a.push(",,,,,,Total,=SUM(I8:I" + (s.length + 6) + "),"),
    a.push(",,,,,,,,");
  for (var o = 0; o < s.length; o++) {
    var n = [],
      i = s[o].querySelectorAll("td, th");
    if (0 == o) var r = "#";
    else r = o;
    for (var l = 0; l < i.length; l++)
      "dataReport" == t && isMobile()
        ? n.push(
            i[l].innerText
              .replace(/Date: | |\n/gi, "")
              .replace(
                /Time:|Username:|IPAddress:|MACAddress:|Profile:|Comment:/gi,
                ","
              )
              .replace(/Address/gi, " Address")
          )
        : n.push(i[l].innerText);
    a.push(r + "," + n.join(","));
  }
  downloadCSV(a.join("\n"), e);
}
function reportFN(e = session) {
  var t =
      $("#filterby-t").html().split("/")[0] +
      "_" +
      $("#filterby-t").html().split("/")[2],
    a = $("#filterby").html().replace(/ /gi, "_");
  return (
    "sales_report" +
    (e = e.replace(/-| /gi, "_")) +
    "_" +
    t +
    (a = t == a ? "" : "_" + a) +
    ".csv"
  );
}
function rFileName(e, t = session) {
  var a =
      $("#filterby-t").html().split("/")[0] +
      "_" +
      $("#filterby-t").html().split("/")[2],
    s = $("#filterby").html().replace(/ /gi, "_");
  return (
    "sales_report" +
    (t = t.replace(/-| /gi, "_")) +
    "_" +
    a +
    (s = a == s ? "" : "_" + s) +
    e
  );
}
function exportToExcel(e, t) {
  var a = "",
    s =
      $("#" + e + " tr")
        .find("td")
        .first()
        .parent()
        .find("td").length + 1,
    o = $("#" + e + " tr").not(".no").length + 6,
    n = "=MAX(A8:A" + o + ")",
    i = "=SUM(I8:I" + o + ")",
    r =
      "<tr><td style='font-weight:bold;text-align:center;' colspan='" +
      s +
      "'>" +
      $("title").html().toUpperCase() +
      "</td></tr>",
    l =
      "<tr><td style='font-weight:bold;text-align:center;' colspan='" +
      s +
      "'>" +
      t.split(".")[0].replace(/_|\?/gi, " ").toUpperCase() +
      "</td></tr>",
    d =
      "<tr><td></td><td></td><td></td><td></td><td></td><td></td><td style='text-align:right;font-weight:bold'>Qty :</td><td style='text-align:right;font-weight:bold'>" +
      n +
      "</td></tr>",
    c =
      "<tr><td></td><td></td><td></td><td></td><td></td><td></td><td style='text-align:right;font-weight:bold'>Amount :</td><td style='text-align:right;font-weight:bold'>" +
      i +
      "</td></tr>";
  $("#" + e + " tr")
    .not(".no")
    .each(function (e, t) {
      (tt = 0 == e ? "<th>#</th>" : "<td>" + e + "</td>"),
        (a += "<tr>" + tt + $(t).html() + "</tr>");
    });
  var m,
    u = {
      worksheet: "Sales Report",
      table: r + l + "<tr><td></td></tr>" + d + c + "<tr><td></td></tr>" + a
    },
    p = document.createElement("a");
  (p.download = t),
    (p.href =
      "data:application/vnd.ms-excel;base64," +
      (function (e) {
        return window.btoa(unescape(encodeURIComponent(e)));
      })(
        ((m = u),
        '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head>\x3c!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--\x3e</head><body><table>{table}</table></body></html>'.replace(
          /{(\w+)}/g,
          function (e, t) {
            return m[t];
          }
        ))
      )),
    p.click();
}
function Theme() {
  $("#Mtheme").hasClass("hide")
    ? ($("#Mtheme").removeClass("hide"), $("#Mtheme").fadeIn("200"))
    : ($("#Mtheme").fadeOut("200"), $("#Mtheme").addClass("hide"));
}
function removeRouter(e) {
  if (confirm("Are you sure want to remove session [" + e + "] ?")) {
    "?admin" == session &&
      "settings" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_a_router.php",
        data: { do: "remove", router_: e },
        dataType: "json",
        success: function (e) {
          "Success" == e.message
            ? (location.href = "./?admin/settings")
            : alert(e.message);
        }
      });
  }
}
function delay(e, t) {
  var a = 0;
  return function () {
    var s = this,
      o = arguments;
    clearTimeout(a),
      (a = setTimeout(function () {
        e.apply(s, o);
      }, t || 0));
  };
}
function saveRouter(e) {
  $("#r-mess").html('<i class="fa fa-cog fa-spin"></i>'),
    $("#r-mess").fadeIn(200),
    localStorage.setItem("?" + e + "_idleto", $("#idleTo").val());
  "?admin" == session &&
    "settings" == path &&
    $.ajax({
      type: "POST",
      url: "./post/post_a_router.php",
      data: {
        do: "save",
        router_: e,
        session: $("#sessName").val().replace("admin", "user01"),
        ipmik: blah($("#ipHost").val()),
        usermik: blah($("#userHost").val()),
        passmik: blah($("#passwdHost").val()),
        hotspotname: $("#hotspotNane").val(),
        dnsname: $("#dnsName").val(),
        currency: $("#currency").val(),
        idleto: $("#idleTo").val(),
        phone: blah($("#phone").val()),
        report: $("#report").val()
      },
      dataType: "json",
      success: function (t) {
        if ("Success" == t.message) {
          $("#r-mess").html("<i class='fa fa-check' aria-hidden='true'></i>");
          var a = setTimeout(function () {
            $("#r-mess").fadeOut(200), $("#r-mess").html(""), clearTimeout(a);
          }, 1500);
          e !== t.sess
            ? (location.href = "./?admin/settings/&r=" + t.sess)
            : $("#sessName").val(e);
        } else
          "__err_" == t.sess
            ? $("#ee-mess").notify(t.message, "error")
            : (alert(t.message),
              (window.location.href = "./?admin/settings/&r=" + t.sess));
      }
    });
}
function genClose() {
  $("#gen_usrqty").val("1"),
    $("#gen_hserver").val($("#gen_hserver  option:first").val()),
    $("#gen_usrmode").val($("#gen_usrmode  option:first").val()),
    $("#gen_namelength").val($("#gen_namelength  option:first").val()),
    $("#gen_usrprefix").val(""),
    $("#gen_char").val($("#gen_char option:first").val()),
    $("#gen_usrprofile").val($("#gen_usrprofile option:first").val()),
    $("#gen_tlimit").val(""),
    $("#gen_dlimit").val(""),
    $("#gen_usrcomm").val(""),
    $("#gen_usrmsg").html(
      '<span class="picon"></span> <span class="pgen"></span> <span class="pprocess"></span>&nbsp;'
    ),
    $("#genact-btn").addClass("hide"),
    $("#btn-genV").css("display", ""),
    $("#btn-genV").attr("onclick", "generateV()");
}
function getAddrPool(e = "true") {
  $("#add_addrpool option").length < 2 &&
    $.getJSON(session + "/get_addr_pool&f=" + e, function (e) {
      $.each(e, function (e, t) {
        $("#add_addrpool").append(
          '<option value="' + t.name + '">' + t.name + "</option>"
        );
      });
    }).always(function () {
      $.getJSON(session + "/get_parent_queue&f=" + e, function (e) {
        $.each(e, function (e, t) {
          $("#add_parentq").append(
            '<option value="' + t.name + '">' + t.name + "</option>"
          );
        });
      });
    });
}
function getHotspotServer() {
  if ($("#gen_hserver option").length < 2) {
    var e = localStorage.getItem(session + "_temp_hotspot_server"),
      t = JSON.parse(e);
    t.length > 0 &&
      $.each(t, function (e, t) {
        $("#gen_hserver").append(
          '<option value="' + t + '" >' + t + "</option>"
        );
      });
  }
}
function getHotspotProfile() {
  if ($("#gen_usrprofile option").length < 2) {
    var e = localStorage.getItem(session + "_temp_user_profiles"),
      t = JSON.parse(e);
    t.length > 0 &&
      $.each(t, function (e, t) {
        $("#gen_usrprofile").append(
          '<option value="' + t + '" >' + t + "</option>"
        );
      });
  }
}
function blah(e) {
  var t = "";
  for (e = btoa(e), e = btoa(e), i = 0; i < e.length; i++) {
    var a = 10 ^ e.charCodeAt(i);
    t += String.fromCharCode(a);
  }
  return (t = btoa(t));
}

function unblah(encoded) {
  try {
    // Step 1: Decode base64 pertama
    let decoded = atob(encoded);

    // Step 2: XOR setiap karakter dengan 10
    let xorResult = "";
    for (let i = 0; i < decoded.length; i++) {
      xorResult += String.fromCharCode(decoded.charCodeAt(i) ^ 10);
    }

    // Step 3: Decode base64 dua kali
    let original = atob(atob(xorResult));

    return original;
  } catch (e) {
    console.error("Gagal decode:", e);
    return null;
  }
}
function noR(e) {
  localStorage.setItem("noR", e),
    1 == localStorage.getItem("noR")
      ? (window.onbeforeunload = function () {
          return !0;
        })
      : (window.onbeforeunload = function () {});
}
function openUser(e, t) {
  $("#usract-btn").show(),
    (upath = "uname" == t ? "/user&name=" : "/user&id="),
    $.get(session + upath + e, function (e) {
      var t = jesD.dec(e);
      if ("[" == t.substring(0, 1)) e = JSON.parse(t);
      else e = JSON.parse(t.substring(1, t.length));
      if (void 0 !== e[0]) {
        var a = e[0].server,
          s = e[0]["limit-bytes-total"],
          o = e[0]["limit-uptime"];
        a || (a = "all"),
          s || (s = ""),
          "MiB" == formatBytes(s).substr(-3, 3)
            ? (audlimit = s / 1048576 + "M")
            : "GiB" == formatBytes(s).substr(-3, 3)
            ? (audlimit = s / 1073741824 + "G")
            : (audlimit = s),
          (limituptime = o ? timeNice(o) : ""),
          $("#usrtt").html(
            '<i class="fa fa-user"></i>&nbsp; <b>' + e[0].name + "</b> &nbsp;"
          ),
          $("#add_hserver").val(a).change(),
          $("#add_usrid").val(e[0][".id"]),
          $("#add_usrname").val(e[0].name),
          $("#add_usrpass").val(e[0].password),
          $("#add_usrprofile").val(e[0].profile).change(),
          $("#add_usrmac").val(e[0]["mac-address"]),
          $("#add_tlimit").val(e[0]["limit-uptime"]),
          $("#add_dlimit").val(audlimit),
          $("#uuptime").val(timeNice(e[0].uptime)),
          $("#ubytesin").val(formatBytes(e[0]["bytes-in"])),
          $("#ubytesout").val(formatBytes(e[0]["bytes-out"])),
          $("#ulimituptime").val(limituptime),
          $("#ulimitbytestotal").val(formatBytes(s));
        var n = e[0].comment;
        n &&
        "/" == n.substr(3, 1) &&
        "/" == n.substr(6, 1) &&
        ":" == n.substr(14, 1) &&
        ":" == n.substr(17, 1)
          ? ($("#usrexp").val(n.substr(0, 22)),
            $("#add_usrcomm").val(n.substr(22, n.length)))
          : (n && "up-" == n.substr(0, 3) && "-" == n.substr(6, 1)) ||
            (n && "vc-" == n.substr(0, 3) && "-" == n.substr(6, 1))
          ? ($("#ucode").val(n.substr(0, 16)),
            $("#add_usrcomm").val(n.substr(16, n.length)))
          : (n && "up-" == n.substr(0, 3)) || (n && "vc-" == n.substr(0, 3))
          ? ($("#ucode").val(n.substr(0, 3)),
            $("#add_usrcomm").val(n.substr(3, n.length)))
          : $("#add_usrcomm").val(n),
          $("#addh_user").fadeIn(),
          noR(1);
      }
    });
}
function resetUser() {
  confirm(
    "Are you sure want to reset user [" + $("#add_usrname").val() + "] ?"
  ) && addUser("yes");
}
function addUser(e = "no") {
  if (
    ($("#add_usrmsg").html(
      "<span class='text-yellow'><i class='fa fa-circle'></i></span> processing..."
    ),
    0 == $("#add_usrid").val())
  )
    var t = "./post/post_add_user.php";
  else t = "./post/post_update_user.php";
  var a = session,
    s = $("#add_dlimit").val(),
    o = $("#add_tlimit").val(),
    n = $("#add_usrcomm").val(),
    i = $("#usrexp").val();
  "M" == s.substr(-1, 1)
    ? (adlimit = 1048576 * Number(s.substr(0, s.length - 1)))
    : "G" == s.substr(-1, 1)
    ? (adlimit = 1073741824 * Number(s.substr(0, s.length - 1)))
    : (adlimit = "" == s ? 0 : s);
  var r,
    l = "";
  (l =
    "" == $("#add_usrmac").val()
      ? "00:00:00:00:00:00"
      : $("#add_usrmac").val()),
    (atlimit = "" == o ? 0 : o),
    "yes" == e &&
      ($("#add_tlimit").val(""),
      $("#usrexp").val(""),
      $("#ucode").val(""),
      (atlimit = 0),
      (n =
        $("#add_usrname").val() == $("#add_usrpass").val()
          ? "vc-" + n
          : "up-" + n),
      (i = "")),
    "?admin" !== session &&
      $.ajax({
        type: "POST",
        url: t,
        data: {
          sessname: a,
          uid: $("#add_usrid").val(),
          server: $("#add_hserver").val(),
          name: $("#add_usrname").val(),
          password: $("#add_usrpass").val(),
          profile: $("#add_usrprofile").val(),
          macaddr: l,
          timelimit: atlimit,
          datalimit: adlimit,
          comment: n,
          expdate: i,
          reset: e,
          ucode: $("#ucode").val()
        },
        dataType: "json",
        success: function (e) {
          if ("success" == e.message) {
            $("#usract-btn").show(),
              $("#add_usrmsg").html(
                "<span class='text-green'><i class='fa fa-circle'></i></span> " +
                  e.message
              ),
              $("#add_usrid").val(e.data[".id"]),
              e.data["mac-address"] && (r = e.data["mac-address"]),
              $("#add_usrmac").val(r);
            var t = e.data.comment;
            t &&
            "/" == t.substr(3, 1) &&
            "/" == t.substr(6, 1) &&
            ":" == t.substr(14, 1) &&
            ":" == t.substr(17, 1)
              ? ($("#usrexp").val(t.substr(0, 20)),
                $("#add_usrcomm").val(t.substr(21, t.length)))
              : (t && "up-" == t.substr(0, 3) && "-" == t.substr(6, 1)) ||
                (t && "vc-" == t.substr(0, 3) && "-" == t.substr(6, 1))
              ? ($("#ucode").val(t.substr(0, 16)),
                $("#add_usrcomm").val(t.substr(16, t.length)))
              : (t && "up-" == t.substr(0, 3)) || (t && "vc-" == t.substr(0, 3))
              ? ($("#ucode").val(t.substr(0, 3)),
                $("#add_usrcomm").val(t.substr(3, t.length)))
              : $("#add_usrcomm").val(t),
              $("#uuptime").val(timeNice(e.data.uptime)),
              $("#ubytesin").val(formatBytes(e.data["bytes-in"])),
              $("#ubytesout").val(formatBytes(e.data["bytes-out"]));
            var s = e.data["limit-uptime"];
            s
              ? ((limituptime = timeNice(s)), $("#add_tlimit").val(s))
              : (limituptime = ""),
              $("#ulimituptime").val(limituptime),
              $("#ulimitbytestotal").val(formatBytes(adlimit)),
              $("#usrtt").html(
                '<i class="fa fa-user"></i>&nbsp; <b>' +
                  e.data.name +
                  "</b> &nbsp;"
              ),
              $.get(a + "/users/&prof=" + e.data.profile + "&f=true").always(
                function () {
                  loadUsersPPF();
                }
              ),
              setTimeout(function () {
                $("#add_usrmsg").html("&nbsp;");
              }, 3e3);
          } else
            $("#add_usrmsg").html(
              "<span class='text-red'><i class='fa fa-circle'></i></span> " +
                e.data.error
            ),
              setTimeout(function () {
                $("#add_usrmsg").html("&nbsp;");
              }, 3e3);
        }
      });
}
function remProf() {
  removeProfile($("#add_usrprofid").val(), $("#add_usrprofname").val());
}
function remUser() {
  removeUser(
    $("#add_usrid").val(),
    $("#add_usrname").val(),
    $("#add_usrprofile").val()
  );
}
function removeUser(e, t, a) {
  if (confirm("Are you sure want to remove user [" + t + "] ?")) {
    var s = session;
    "?admin" !== session &&
      "hotspot" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_hotspot_remove.php",
        data: { sessname: s, where: "user_", id: e },
        dataType: "json",
        success: function (e) {
          "success" == e.message
            ? $.get(s + "/users/&prof=" + a + "&f=true").always(function () {
                loadUsersPPF();
              })
            : location.reload();
        }
      }),
      $("#huClose").click();
  }
}
function openUserProfile(e, t) {
  $("#usrprofact-btn").show(),
    (upath = "upname" == t ? "/profile&name=" : "/profile&id="),
    getAddrPool("false"),
    $.getJSON(session + upath + e, function (e) {
      var t = "",
        a = "none",
        s = "none",
        o = "none",
        n = "",
        i = "",
        r = "",
        l = "Disable",
        d = "Disable";
      void 0 !== e[0] &&
        (e[0]["on-login"] &&
          ((o = (t = e[0]["on-login"]).split(",")[1]),
          (n = t.split(",")[3]),
          (i = t.split(",")[2]),
          (r = t.split(",")[4]),
          (l = t.split(",")[6]),
          (d = t.split(",")[7]),
          (none = t.split(",")[5]),
          "noexp" == none && (o = "0"),
          "" == d.split('")')[0] && (d = "Disable")),
        e[0]["address-pool"] && (a = e[0]["address-pool"]),
        e[0]["rate-limit"] && e[0].ratelimit,
        e[0]["rate-limit"] && e[0].ratelimit,
        e[0]["parent-queue"] && (s = e[0]["parent-queue"]),
        $("#usrproftt").html(
          '<i class="fa fa-pie-chart"></i>&nbsp; <b>' +
            e[0].name +
            "</b> &nbsp;"
        ),
        $("#add_usrprofid").val(e[0][".id"]),
        $("#add_usrprofname").val(e[0].name),
        $("#add_addrpool").val(a),
        $("#add_sharedusr").val(e[0]["shared-users"]),
        $("#add_ratelimit").val(e[0]["rate-limit"]),
        $("#add_parentq").val(s),
        $("#add_expmode").val(o),
        $("#add_validity").val(n),
        $("#add_price").val(i),
        $("#add_sellingprice").val(r),
        $("#add_lockuser").val(l),
        $("#add_lockserver").val(d),
        $("#usrprofact-btn").show(),
        $("#addh_userprofile").fadeIn(),
        noR(1));
    });
}
function addUserProfile() {
  if (
    ($("#add_usrprofilemsg").html(
      "<span class='text-yellow'><i class='fa fa-circle'></i></span> processing..."
    ),
    0 == $("#add_usrprofid").val())
  )
    var e = "./post/post_add_userprofile.php";
  else e = "./post/post_update_userprofile.php";
  var t = session;
  "?admin" !== session &&
    $.ajax({
      type: "POST",
      url: e,
      data: {
        sessname: t,
        upid: $("#add_usrprofid").val(),
        name: $("#add_usrprofname").val(),
        addresspool: $("#add_addrpool").val(),
        sharedusers: $("#add_sharedusr").val(),
        ratelimit: $("#add_ratelimit").val(),
        parentqueue: $("#add_parentq").val(),
        expmode: $("#add_expmode").val(),
        validity: $("#add_validity").val(),
        price: $("#add_price").val(),
        sellingprice: $("#add_sellingprice").val(),
        lockuser: $("#add_lockuser").val(),
        lockserver: $("#add_lockserver").val()
      },
      dataType: "json",
      success: function (e) {
        "success" == e.message
          ? ($("#usrprofact-btn").show(),
            $("#add_usrprofilemsg").html(
              "<span class='text-green'><i class='fa fa-circle'></i></span> " +
                e.message
            ),
            $("#add_usrprofid").val(e.data[".id"]),
            $("#usrproftt").html(
              '<i class="fa fa-pie-chart"></i>&nbsp; <b>' +
                e.data.name +
                "</b> &nbsp;"
            ),
            $.get(t + "/profiles&f=true").always(function () {
              loadUserProfiles();
            }),
            setTimeout(function () {
              $("#add_usrprofilemsg").html("&nbsp;");
            }, 3e3))
          : ($("#add_usrprofilemsg").html(
              "<span class='text-red'><i class='fa fa-circle'></i></span> " +
                e.data.error
            ),
            setTimeout(function () {
              $("#add_usrprofilemsg").html("&nbsp;");
            }, 3e3));
      }
    });
}
function removeProfile(e, t) {
  if (confirm("Are you sure want to remove profile [" + t + "] ?")) {
    var a = session;
    "?admin" !== session &&
      "hotspot" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_hotspot_remove.php",
        data: { sessname: a, where: "profile_", id: e },
        dataType: "json",
        success: function (e) {
          "success" == e.message ? loadUserProfiles("true") : location.reload();
        }
      }),
      $("#hupClose").click();
  }
}
function removeActive(e, t) {
  if (confirm("Are you sure want to remove user [" + t + "] ?")) {
    var a = session;
    "?admin" !== session &&
      "hotspot" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_hotspot_remove.php",
        data: { sessname: a, where: "active_", id: e },
        dataType: "json",
        success: function (e) {
          "success" == e.message
            ? (clearInterval(window.auto_hotspot_active), loadHotspotActive())
            : location.reload();
        }
      });
  }
}
function removeHost(e, t) {
  if (confirm("Are you sure want to remove MAC [" + t + "] ?")) {
    var a = session;
    "?admin" !== session &&
      "hotspot" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_hotspot_remove.php",
        data: { sessname: a, where: "host_", id: e },
        dataType: "json",
        success: function (e) {
          "success" == e.message
            ? (clearInterval(window.auto_hotspot_hosts), loadHotspotHosts())
            : location.reload();
        }
      });
  }
}
function setExpMon() {
  var e = session;
  "?admin" !== session &&
    $.ajax({
      type: "POST",
      url: "./post/post_expire_monitor.php",
      data: {
        sessname: e,
        expmon:
          ':local dateint do={:local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );:local days [ :pick $d 4 6 ];:local month [ :pick $d 0 3 ];:local year [ :pick $d 7 11 ];:local monthint ([ :find $montharray $month]);:local month ($monthint + 1);:if ( [len $month] = 1) do={:local zero ("0");:return [:tonum ("$year$zero$month$days")];} else={:return [:tonum ("$year$month$days")];}};:local timeint do={:local hours [ :pick $t 0 2 ];:local minutes [ :pick $t 3 5 ];:return ($hours * 60 + $minutes) ;};:local date [ /system clock get date ];:local time [ /system clock get time ];:local today [$dateint d=$date] ;:local curtime [$timeint t=$time] ;:local tyear [ :pick $date 7 11 ];:local lyear ($tyear-1);:foreach i in [ /ip hotspot user find where comment~"/$tyear" || comment~"/$lyear" ] do={:local comment [ /ip hotspot user get $i comment]; :local limit [ /ip hotspot user get $i limit-uptime]; :local name [ /ip hotspot user get $i name]; :local gettime [:pic $comment 12 20];:if ([:pic $comment 3] = "/" and [:pic $comment 6] = "/") do={:local expd [$dateint d=$comment] ;:local expt [$timeint t=$gettime] ;:if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime) and $limit != "00:00:01") do={ :if ([:pic $comment 21] = "N") do={[ /ip hotspot user set limit-uptime=1s $i ];[ /ip hotspot active remove [find where user=$name] ];} else={[ /ip hotspot user remove $i ];[ /ip hotspot active remove [find where user=$name] ];}}}}}'
      },
      dataType: "json",
      success: function (e) {
        "success" == e.message &&
          ($("#exp_mon").html(
            '<i class="fa fa-ci fa-circle text-green" title="Expire users monitor is activated."></i>'
          ),
          $("#btn-exp-mon").fadeOut(200));
      }
    });
}
function setCharacter() {
  "up" == $("#gen_usrmode").val()
    ? ($("#lower").removeClass("hide"),
      $("#upper").removeClass("hide"),
      $("#upplow").removeClass("hide"),
      $("#mix").removeClass("hide"),
      $("#mix1").removeClass("hide"),
      $("#mix2").removeClass("hide"),
      $("#upper1").addClass("hide"),
      $("#lower1").addClass("hide"),
      $("#upplow1").addClass("hide"),
      $("#num").addClass("hide"),
      $("#gen_char").val($("#gen_char option:first").val()),
      $("#gen_namelength").val("4"))
    : "vc" == $("#gen_usrmode").val() &&
      ($("#lower").addClass("hide"),
      $("#upper").addClass("hide"),
      $("#upplow").addClass("hide"),
      $("#mix").removeClass("hide"),
      $("#mix1").removeClass("hide"),
      $("#mix2").removeClass("hide"),
      $("#upper1").removeClass("hide"),
      $("#lower1").removeClass("hide"),
      $("#upplow1").removeClass("hide"),
      $("#num").removeClass("hide"),
      $("#gen_char").val($("#lower1").val()),
      $("#gen_namelength").val("8"));
}
function generateV() {
  $("#btn-genV").attr("onclick", ""),
    localStorage.setItem(session + "_gencode", randomN(101, 999));
  var e = $("#gen_usrqty").val();
  localStorage.setItem(session + "_totgenv", e),
    localStorage.setItem(session + "_genleft", e),
    genVoucher();
}
function genVoucher() {
  $(".picon").html("<i class='fa fa-circle-o-notch fa-spin text-yellow'></i>"),
    $(".pprocess").html("Generating...");
  var e = localStorage.getItem(session + "_genleft");
  qty = e > 50 ? 50 : e;
  var t = session;
  "?admin" !== session &&
    $.ajax({
      type: "POST",
      url: "./post/post_generate_voucher.php",
      data: {
        sessname: t,
        qty: qty,
        server: $("#gen_hserver").val(),
        user: $("#gen_usrmode").val(),
        userl: $("#gen_namelength").val(),
        prefix: $("#gen_usrprefix").val(),
        char: $("#gen_char").val(),
        profile: $("#gen_usrprofile").val(),
        timelimit: $("#gen_tlimit").val(),
        datalimit: $("#gen_dlimit").val(),
        gcomment: $("#gen_usrcomm").val(),
        gencode: localStorage.getItem(session + "_gencode")
      },
      dataType: "json",
      success: function (e) {
        if ("success" == e.message) {
          $(".picon").html(
            "<i class='fa fa-circle-o-notch fa-spin text-green'></i>"
          ),
            $(".pgen").html(e.data.count + " of " + $("#gen_usrqty").val()),
            $(".pprocess").html("");
          var a =
            localStorage.getItem(session + "_totgenv") - Number(e.data.count);
          localStorage.setItem(session + "_genleft", a),
            localStorage.setItem(session + "_gencomment", e.data.comment),
            a > 0
              ? setTimeout(() => {
                  genVoucher();
                }, 1e3)
              : (genCacheVoucher(),
                $("#genact-btn").removeClass("hide"),
                localStorage.setItem(session + "_gencode", ""),
                $("#btn-genV").css("display", "none"),
                $(".picon").html("<i class='fa fa-circle text-green'></i>"),
                $(".pgen").html(e.data.count),
                $(".pprocess").html("Done"),
                cacheUser(t, e.data.profile));
        } else
          $(".picon").html("<i class='fa fa-circle text-red'></i>"),
            $(".pprocess").html(e.data.error),
            setTimeout(function () {
              $("#gen_usrmsg").html(
                '<span class="picon"></span> <span class="pgen"></span> <span class="pprocess"></span>&nbsp;'
              );
            }, 3e3);
      }
    });
}
function cacheUser(e, t) {
  $.get(e + "/users/&prof=" + t + "&f=true").always(function () {
    loadUsersPPF();
  });
}
function genCacheVoucher() {
  var e = session;
  "?admin" !== session &&
    $.ajax({
      type: "POST",
      url: "./post/post_cache_voucher.php",
      data: {
        sessname: e,
        qty: $("#gen_usrqty").val(),
        user: $("#gen_usrmode").val(),
        gcomment: $("#gen_usrcomm").val(),
        gencode: localStorage.getItem(session + "_gencode")
      },
      dataType: "json",
      success: function (e) {
        "success" == e.message
          ? console.log(e.data.count)
          : console.log(e.data.error);
      }
    });
}
function editRouter(e) {
  location.href = "./?admin/settings/&r=" + e;
}
function getInterval() {
  return localStorage.getItem(session + "_auto_traffic");
}
function fileUpChk(e) {
  "png" !== e.files[0].name.split(".").pop()
    ? alert("Please choose .png file type.")
    : e.files[0].size / 1024 / 1024 > 1
    ? alert("File to large [" + formatBytes(e.files[0].size) + "], max 1 MiB.")
    : $("#tempfileup").html(
        e.files[0].name + " [" + formatBytes(e.files[0].size) + "]"
      );
}
$("#month").change(function () {
  $("#btnFilter").attr(
    "title",
    "Filter " + $("#month").val().capitalize() + " " + $("#year").val()
  );
}),
  $("#year").change(function () {
    $("#btnFilter").attr(
      "title",
      "Filter " + $("#month").val().capitalize() + " " + $("#year").val()
    );
  }),
  $("#logOut").click(function () {
    $.ajax({
      type: "POST",
      url: "./post/post_logout.php",
      data: { logout: session },
      success: function (e) {
        session === e &&
          (localStorage.setItem("typeTemplate", ""),
          localStorage.setItem(session + "_cache_user_profiles", ""),
          location.replace("./?login"));
      }
    });
  }),
  $("#btn-add-router").click(function () {
    $("#add-router-message").html("Creating a new config..."),
      $("#add-router-message").fadeIn(200);
    "?admin" == session &&
      "settings" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_a_router.php",
        data: { do: "add", router_: "sess_" },
        dataType: "json",
        success: function (e) {
          "Success" == e.message
            ? (location.href = "./?admin/settings/&r=" + e.sesname)
            : ($("#add-router-message").css({ width: "500px" }),
              $("#add-router-message").html(e.message),
              $("#add-router-message").fadeIn(200),
              setTimeout(function () {
                $("#add-router-message").fadeOut(200);
              }, 5e3));
        }
      });
  }),
  $(".asave").keyup(
    delay(function (e) {
      "" !== $("#sessName").val().trim() && saveRouter($("#sessName").val());
    }, 1e3)
  ),
  $("#idleTo").change(
    delay(function (e) {
      "" !== $("#sessName").val().trim() && saveRouter($("#sessName").val());
    }, 1e3)
  ),
  $("#report").change(
    delay(function (e) {
      "" !== $("#sessName").val().trim() && saveRouter($("#sessName").val());
    }, 1e3)
  ),
  $("#sessName").keyup(
    delay(function (e) {
      "" !== $("#sessName").val().trim() && $("#saveR").click();
    }, 1e3)
  ),
  $(".adm").keyup(
    delay(function (e) {
      $("#btn-asave").click();
    }, 1e3)
  ),
  $("#rConnect").click(function () {
    connect("ee-mess", $("#sessName").val());
  }),
  $("#saveR").click(function () {
    var e = new URL(window.location).searchParams.get("r");
    "" !== $("#sessName").val() ? saveRouter(e) : $("#sessName").focus();
  }),
  $("#btn-asave").click(function () {
    $("#a-mess").html('<i class="fa fa-cog fa-spin"></i>'),
      $("#a-mess").fadeIn(200);
    "?admin" == session &&
      "settings" == path &&
      $.ajax({
        type: "POST",
        url: "./post/post_a_router.php",
        data: {
          do: "saveAdmin",
          router_: session,
          username: blah($("#userAdm").val()),
          password: blah($("#passAdm").val())
        },
        dataType: "json",
        success: function (e) {
          "Success" == e.message
            ? ($("#a-mess").html(
                "<i class='fa fa-check' aria-hidden='true'></i>"
              ),
              setTimeout(function () {
                $("#a-mess").fadeOut(200);
              }, 1e3))
            : ($("#e-mess").html(e.message),
              $("#e-mess").fadeIn(200),
              setTimeout(function () {
                $("#e-mess").fadeOut(200),
                  $("#a-mess").fadeOut(200),
                  $("#e-mess").html("");
              }, 3e3));
        }
      });
  }),
  $("#btn-mm-admin").click(function () {
    menuActive("mm-admin"),
      menuNonActive("router-list"),
      menuNonActive("edit-router"),
      isMobile() && $("#pload").html($("#btn-mm-admin").html());
  }),
  $("#btn-router-list").click(function () {
    menuNonActive("mm-admin"),
      menuNonActive("edit-router"),
      menuActive("router-list"),
      isMobile() && $("#pload").html($("#btn-router-list").html());
  }),
  $("#btn-edit-router").click(function () {
    menuNonActive("mm-admin"),
      menuNonActive("router-list"),
      menuActive("edit-router"),
      isMobile() && $("#pload").html($("#btn-edit-router").html());
  }),
  $("#btn-general").click(function () {
    $("#btn-details").removeClass("card-tab-active"),
      $("#udetails").addClass("hide"),
      $("#btn-general").addClass("card-tab-active"),
      $("#ugeneral").removeClass("hide");
  }),
  $("#btn-details").click(function () {
    $("#btn-details").addClass("card-tab-active"),
      $("#udetails").removeClass("hide"),
      $("#btn-general").removeClass("card-tab-active"),
      $("#ugeneral").addClass("hide");
  }),
  $("#btn-upgeneral").click(function () {
    $("#btn-updetails").removeClass("card-tab-active"),
      $("#updetails").addClass("hide"),
      $("#btn-upgeneral").addClass("card-tab-active"),
      $("#upgeneral").removeClass("hide");
  }),
  $("#btn-updetails").click(function () {
    $("#btn-updetails").addClass("card-tab-active"),
      $("#updetails").removeClass("hide"),
      $("#btn-upgeneral").removeClass("card-tab-active"),
      $("#upgeneral").addClass("hide");
  }),
  $("#btn-gengeneral").click(function () {
    $("#btn-gendetails").removeClass("card-tab-active"),
      $("#gendetails").addClass("hide"),
      $("#btn-gengeneral").addClass("card-tab-active"),
      $("#gengeneral").removeClass("hide");
  }),
  $("#btn-gendetails").click(function () {
    $("#btn-gendetails").addClass("card-tab-active"),
      $("#gendetails").removeClass("hide"),
      $("#btn-gengeneral").removeClass("card-tab-active"),
      $("#gengeneral").addClass("hide");
  }),
  $(".btn-add-hotspot-users").click(function () {
    $("#addh_user").fadeIn(), noR(1);
  }),
  $(".btn-add-user-profiles").click(function () {
    getAddrPool(), $("#addh_userprofile").fadeIn(), noR(1);
  }),
  $(".btn-gen-hotspot-users").click(function () {
    getHotspotServer(),
      getHotspotProfile(),
      $("#gen_user").fadeIn(),
      localStorage.setItem(session + "_gencode", ""),
      noR(1);
  }),
  $("#genClose").click(function () {
    "" !== localStorage.getItem(session + "_gencode")
      ? noR(1)
      : "" == localStorage.getItem(session + "_gencode") &&
        ($("#gen_user").fadeOut(), genClose(), noR(0));
  }),
  $("#huClose").click(function () {
    $("#addh_user").fadeOut(),
      setTimeout(function () {
        $("#btn-general").click(),
          $("#add_hserver").val($("#add_hserver option:first").val()),
          $("#add_usrid").val("0"),
          $("#add_usrname").val(""),
          $("#add_usrpass").val(""),
          $("#add_usrprofile").val($("#add_usrprofile option:first").val()),
          $("#add_usrmac").val(""),
          $("#add_tlimit").val(""),
          $("#add_dlimit").val(""),
          $("#add_usrcomm").val(""),
          $("#uuptime").val(""),
          $("#ubytesin").val(""),
          $("#ubytesout").val(""),
          $("#ulimituptime").val(""),
          $("#ulimitbytestotal").val(""),
          $("#usrexp").val(""),
          $("#ucode").val(""),
          $("#usract-btn").hide(),
          $("#usrtt").html(
            '<i class="fa fa-user-plus"></i>&nbsp; <b>Add User</b> &nbsp;'
          );
      }, 700),
      noR(0);
  }),
  $("#hupClose").click(function () {
    $("#addh_userprofile").fadeOut(),
      setTimeout(function () {
        $("#btn-upgeneral").click(),
          $("#add_usrprofid").val("0"),
          $("#add_usrprofname").val(""),
          $("#add_adrpool").val($("#add_addrpool option:first").val()),
          $("#add_sharedusr").val("1"),
          $("#add_ratelimit").val(""),
          $("#add_expmode").val($("#add_expmode option:first").val()),
          $("#add_validity").val(""),
          $("#add_price").val(""),
          $("#add_sellingprice").val(""),
          $("#add_lockuser").val($("#add_lockuser option:first").val()),
          $("#add_parentqueue").val($("#add_parentqueue option:first").val()),
          $("#usrprofact-btn").hide(),
          $("#usrproftt").html(
            '<i class="fa fa-pie-chart"></i>&nbsp; <b>Add Profile</b> &nbsp;'
          );
      }, 700),
      noR(0);
  }),
  $("#add_usrprofname").keypress(function (e) {
    var t = e.which;
    return (
      32 == t ||
      (48 <= t && t <= 57) ||
      (65 <= t && t <= 90) ||
      (97 <= t && t <= 122)
    );
  }),
  $("#gen_usrcomm").keypress(function (e) {
    var t = e.which;
    return (
      32 == t ||
      (48 <= t && t <= 57) ||
      (65 <= t && t <= 90) ||
      (97 <= t && t <= 122)
    );
  }),
  $("#gen_usrmode").change(function () {
    setCharacter();
  }),
  $(".gen_clear").click(function () {
    genClose();
  }),
  $(".print_v").click(function () {
    pVcr(localStorage.getItem(session + "_gencomment"), "d");
  }),
  $(".print_vs").click(function () {
    pVcr(localStorage.getItem(session + "_gencomment"), "s");
  }),
  $("#browsefile").click(function () {
    $("#fileup").click();
  });
var sq = location.hostname.split(".");
if ("?admin" == session && "template_editor" == path) {
  var editor = CodeMirror.fromTextArea(
    document.getElementById("editorMikhmon"),
    {
      lineNumbers: !0,
      matchBrackets: !0,
      mode: "application/x-httpd-php",
      indentUnit: 4,
      indentWithTabs: !0,
      lineWrapping: !0,
      viewportMargin: 1 / 0,
      matchTags: { bothTags: !0 },
      autoCloseBrackets: !0,
      autoCloseTags: !0,
      keyMap: "sublime",
      extraKeys: { "Ctrl-J": "toMatchingTag" }
    }
  );
  editor.setOption("theme", "material"), editor.refresh();
}
function loadTemplate(e) {
  $(
    "#select-template option[value=" +
      localStorage.getItem("nameTemplate") +
      "]"
  ).attr("selected", "selected"),
    localStorage.setItem("typeTemplate", e),
    localStorage.setItem("nameTemplate", $("#select-template").val());
  var t = localStorage.getItem("typeTemplate"),
    a = localStorage.getItem("nameTemplate");
  localStorage.setItem("tmplPath", t + "." + a + ".txt"),
    $.get(
      "./template/" + t + "." + a + ".txt?" + new Date().getTime(),
      function (e) {
        editor.setValue(e), editor.refresh();
      }
    ).always(function () {
      "header" == e
        ? $("#active-edit").html("/template/" + a + "." + t + ".txt")
        : "row" == e
        ? $("#active-edit").html("/template/" + a + "." + t + ".txt")
        : "footer" == e &&
          $("#active-edit").html("/template/" + a + "." + t + ".txt"),
        $("#select-template").addClass("btn-group-active");
    });
}
function mNav() {
  var e = document.getElementById("mnav");
  "navbar" === e.className
    ? ((e.className += " nav_responsive"),
      $("#mactive").hide(),
      $("#Mtheme").removeClass("hide"))
    : ((e.className = "navbar"),
      $("#mactive").show(),
      $("#Mtheme").addClass("hide"));
}
function hidemNav() {
  (document.getElementById("mnav").className = "navbar"),
    $("#mactive").show(),
    $("#Mtheme").addClass("hide");
}
function dropD() {
  $("#mDropdown").hasClass("show")
    ? $("#mDropdown").removeClass("show")
    : $("#mDropdown").addClass("show");
}
$("#btn-tsave").click(function () {
  $("#loading").html('<i class="fa fa-cog fa-spin"></i>'),
    $("#loading").fadeIn(200);
  var e = localStorage.getItem("tmplPath");
  "?admin" == session &&
    "template_editor" == path &&
    $.ajax({
      type: "POST",
      url: "./post/post_template.php",
      data: {
        do: "saveTemplate",
        router_: session,
        file_: e,
        _template: editor.getValue()
      },
      dataType: "json",
      success: function (e) {
        "Saved" == e.message
          ? ($("#loading").html(
              " <i class='fa fa-check' aria-hidden='true'></i>"
            ),
            setTimeout(function () {
              $("#loading").fadeOut(200), $("#loading").html("&nbsp;");
            }, 1e3))
          : $("#t_err").notify(e.message, "error");
      }
    });
}),
  $("#select-template").change(function () {
    loadTemplate("row", this.value);
  }),
  isMobile() &&
    $(document).click() &&
    $("#mDropdown a").click(function () {
      $("#mDropdown").removeClass("show");
    }),
  $("#sessionslk").click(function (e) {
    e.stopPropagation();
  }),
  $("#lsession").click(function (e) {
    e.stopPropagation();
  }),
  isMobile() &&
    $(document).click(function () {
      hidemNav(), $("#mDropdown").removeClass("show");
    }),
  $(".mNavBtn").click(function (e) {
    e.stopPropagation(), mNav(), $("#mDropdown").removeClass("show");
  }),
  $(".dropbtn").click(function (e) {
    e.stopPropagation(), dropD(), hidemNav();
  }),
  $("#add_expmode").change(function () {
    0 == $("#add_expmode").val()
      ? $("#trvalidity").addClass("hide")
      : ($("#trvalidity").removeClass("hide"), $("#add_validity").focus());
  }),
  $("#hTimeLimit").click(function () {
    $("#hTimeLimit").notify(
      "format: d,h,m or s\nexample:\n1d (1 day)\n1h (2 hour)\n10m (10 minutes)\n30s (30 seconds)",
      "info"
    );
  }),
  $("#hDataLimit").click(function () {
    $("#hDataLimit").notify(
      "format: M or G\nexample:\n5G (5 GiB) \n1500M (1500 MiB)\n 1MiB = 1024KiB",
      "info"
    );
  }),
  $("#ghTimeLimit").click(function () {
    $("#ghTimeLimit").notify(
      "format: d,h,m or s\nexample:\n1d (1 day)\n1h (2 hour)\n10m (10 minutes)\n30s (30 seconds)",
      "info"
    );
  }),
  $("#ghDataLimit").click(function () {
    $("#ghDataLimit").notify(
      "format: M or G\nexample:\n5G (5 GiB) \n1500M (1500 MiB)\n 1MiB = 1024KiB",
      "info"
    );
  }),
  $("#hRateLimit").click(function () {
    $("#trratelimit").notify("example: 512k/1M", "info");
  }),
  $("#hExpMode").click(function () {
    $("#trexpmode").notify(
      "None: User without expiry time.\nRemove: User will be deleted after expiry time.\nNotice: user can't login after time expired\nand get notification after trying to login again.\nRecord: Record user data after login.",
      { className: "info", autoHide: !1 }
    );
  }),
  $("#hValidity").click(function () {
    $("#trvalidity").notify(
      "format: d or w\nexample:\n1d (1 day), 30d (30 days)\n1w (7 days)",
      "info"
    );
  }),
  $("#hLockUser").click(function () {
    $("#trlockuser").notify(
      "Lock the MAC Address of user after login.",
      "info"
    );
  }),
  $("#hLockServer").click(function () {
    $("#trlockuser").notify(
      "Lock the Server Hotspot of user after login.",
      "info"
    );
  }),
  $(".editor_help").click(function () {
    window.open("https://laksa19.github.io/?mikhmon/v4/voucher");
  });
