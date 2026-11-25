(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var open = document.getElementById("openNav");
    var close = document.getElementById("closeNav");
    if (open) { open.addEventListener("click", function () { document.body.classList.add("mm-nav-open"); }); }
    if (close) { close.addEventListener("click", function () { document.body.classList.remove("mm-nav-open"); }); }
    window.addEventListener("resize", function () {
      if (window.innerWidth > 750) { document.body.classList.remove("mm-nav-open"); }
    });
  });

  function closeModalByHash() {
    if (window.location.hash) {
      history.replaceState(null, document.title, window.location.pathname + window.location.search);
    }
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      var open = document.querySelector(".modal-window:target");
      if (open) { closeModalByHash(); }
    }
  });

  document.addEventListener("click", function (event) {
    var target = event.target;
    if (!(target instanceof Element)) { return; }
    if (target.matches(".modal-window") || target.matches(".modal-window .modal-close")) {
      if (target.matches(".modal-close")) { event.preventDefault(); }
      closeModalByHash();
    }
  });

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("form.mm-toolbar").forEach(function (form) {
      form.addEventListener("submit", function () {
        form.querySelectorAll("input[name], select[name]").forEach(function (field) {
          if (field.type === "hidden") { return; }
          if (field.value === "") { field.disabled = true; }
        });
      });
    });

    // Turn wide legacy tables into horizontally scrollable regions.
    document.querySelectorAll("table.table").forEach(function (table) {
      var parent = table.parentElement;
      if (!parent || parent.classList.contains("overflow") || parent.classList.contains("mm-table-wrap")) { return; }
      if (table.closest(".sidenav")) { return; }
      parent.classList.add("mm-table-wrap");
    });
  });

  window.MikhmonUI = { closeModalByHash: closeModalByHash };
})();
