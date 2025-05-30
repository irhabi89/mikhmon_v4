var url = window.location.href;
if (url.indexOf("?") > 0) var session = "?" + url.split("?")[1].split("/")[0];
var currency = localStorage.getItem(session + "_curr");
function formatBytes(t) {
  if (0 == t) return "";
  var e = parseInt(Math.floor(Math.log(t) / Math.log(1024)));
  return (
    parseFloat((t / Math.pow(1024, e)).toFixed(2)) +
    " " +
    ["Byte", "KiB", "MiB", "GiB", "TiB"][e]
  );
}
function priceFormat(t, e = "$") {
  return "Rp" == e || "RP" == e || "IDR" == e || "rp" == e
    ? t
        .toFixed(0)
        .replace(".", ",")
        .replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.")
    : t.toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
}
function currencyFormat(t, e = "$") {
  return "Rp" == e || "RP" == e || "IDR" == e || "rp" == e
    ? "<span>" +
        e +
        "&nbsp;</span>" +
        t
          .toFixed(0)
          .replace(".", ",")
          .replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.")
    : e + "&nbsp" + t.toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
}
function currencyFormatSF(t, e = "$") {
  if (
    (isMobile() ? (mCFSF = 10) : (mCFSF = 20),
    "Rp" == e || "RP" == e || "IDR" == e || "rp" == e)
  ) {
    var s = t
      .toFixed(0)
      .replace(".", ",")
      .replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    return (
      (numL = s.split(".").length),
      1 == numL || 2 == numL
        ? ((a =
            "<span style='font-size:" +
            mCFSF +
            "px;'>" +
            s.split(".")[0] +
            "</span>"),
          (b = s.substring(s.split(".")[0].length)),
          s.length)
        : 3 == numL &&
          ((a =
            "<span style='font-size:" +
            mCFSF +
            "px;'>" +
            s.split(".")[0] +
            "." +
            s.split(".")[1] +
            "</span>"),
          (b = "." + s.split(".")[2])),
      "<span style='font-size:15px;position:absolute;margin-left:-20px;'>" +
        e +
        "&nbsp;</span>" +
        a +
        b
    );
  }
  s = t.toFixed(2).replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1,");
  return (
    (a = "<span style='font-size:40px;'>" + s.split(",")[0] + "</span>"),
    (b = s.substring(s.split(",")[0].length)),
    s.length,
    "<span style='font-size:15px;position:absolute;margin-left:-20px;'>" +
      e +
      "&nbsp;</span>" +
      a +
      b
  );
}
function nFormat(t) {
  return "<span style='font-size:40px;'>" + t + "</span>";
}
function nFormatMobile(t) {
  return "<span style='font-size:25px;'>" + t + "</span>";
}
function timeNice(t) {
  var e = t.search("w"),
    s = t.search("d"),
    n = t.search("h"),
    r = t.search("m"),
    i = t.search("s");
  e > 0
    ? ((weak = 7 * Number(t.split("w")[0])),
      (t_day = t.substring(e + 1, t.legth)))
    : e < 0 && ((weak = ""), (t_day = t.substring(t.legth))),
    s > 0
      ? (weak > 0
          ? (day = Number(t_day.split("d")[0]))
          : (day = t_day.split("d")[0]),
        (t_hour = t.substring(s + 1, t.legth)))
      : s < 0 && ((day = ""), (t_hour = t_day.substring(t.legth))),
    n > 0
      ? ((hour = t_hour.split("h")[0]),
        1 == hour.length ? (hour = "0" + hour + ":") : (hour += ":"),
        (t_minute = t.substring(n + 1, t.legth)))
      : n < 0 && ((hour = "00:"), (t_minute = t.substring(s + 1, t.legth))),
    r > 0
      ? ((minute = t_minute.split("m")[0]),
        1 == minute.length && (minute = "0" + minute),
        (t_sec = t.substring(r + 1, t.legth)))
      : r < 0 && n < 0
      ? ((minute = "00"), (t_sec = t.substring(s + 1, t.legth)))
      : r < 0 && ((minute = "00"), (t_sec = t.substring(n + 1, t.legth))),
    i > 0
      ? ((sec = t_sec.split("s")[0]),
        1 == sec.length ? (sec = ":0" + sec) : (sec = ":" + sec))
      : i < 0 && (sec = ":00");
  var a = Number(weak) + Number(day);
  return a < 1 ? (a = "") : (a += "d "), a + hour + minute + sec;
}
function ucFirst(t) {
  return (
    (a = t.substring(0, 1)), (b = t.substring(1, t.length)), a.toUpperCase() + b
  );
}
function niceBytes(t, e = 2) {
  if (0 === t) return "0 Bytes";
  const s = e < 0 ? 0 : e,
    n = Math.floor(Math.log(t) / Math.log(1024));
  return (
    parseFloat((t / Math.pow(1024, n)).toFixed(s)) +
    " " +
    ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"][n]
  );
}
