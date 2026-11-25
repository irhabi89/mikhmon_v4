(function () {
  "use strict";

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

  // Turn wide legacy tables into horizontally scrollable regions.
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("table.table").forEach(function (table) {
      var parent = table.parentElement;
      if (!parent || parent.classList.contains("overflow") || parent.classList.contains("mm-table-wrap")) { return; }
      if (table.closest(".sidenav")) { return; }
      parent.classList.add("mm-table-wrap");
    });
  });

  window.MikhmonUI = { closeModalByHash: closeModalByHash };
})();
