(function () {
  "use strict";

  function parseFloatSafe(v) {
    return v === undefined || v === null || v === "" ? 0 : parseFloat(v);
  }

  function calculateInitialRent() {
    var valuation = parseFloatSafe(
      document.getElementById("ltl_valuation_amount").value
    );
    var pct = parseFloatSafe(
      document.getElementById("ltl_annual_rent_percentage").value
    );
    var initial = valuation * (pct / 100);
    document.getElementById("ltl_initial_rent").value = initial
      ? Number(initial).toFixed(2)
      : "";

    // document.getElementById("ltl_initial_rent").value = initial
    //   ? "Rs. " + initial.toLocaleString("en-US", { minimumFractionDigits: 2 })
    //   : "";
    // Premium logic: if start date < 2020-01-01 then premium = 3x initial rent
    try {
      var sd = document.getElementById("ltl_start_date").value;
      var ltl_lease_type = document.getElementById("ltl_lease_type").value;
      var premiumRow = document.getElementById("ltl_premium_row");
      var premiumEl = document.getElementById("ltl_premium");

      if (sd && premiumRow && premiumEl) {
        if (sd < "2020-01-01" && initial > 0 && ltl_lease_type < 5) {
          premiumRow.style.display = "";
          premiumEl.value = (initial * 3).toLocaleString("en-US", {
            minimumFractionDigits: 2,
          });
        } else {
          premiumRow.style.display = "none";
          premiumEl.value = "";
        }
      }
    } catch (e) {}
  }

  function calculateEndDate() {
    var sd = document.getElementById("ltl_start_date").value;
    var years =
      parseInt(document.getElementById("ltl_duration_years").value) || 0;
    var endEl = document.getElementById("ltl_end_date");
    var durEl = document.getElementById("ltl_calculated_duration");
    if (sd && years > 0) {
      var s = new Date(sd);
      var e = new Date(s);
      e.setFullYear(e.getFullYear() + years);
      endEl.value = e.toISOString().split("T")[0];
      durEl.value = years + " Years";
    } else {
      // If we have both dates but no explicit years, compute duration from dates; else leave end date as-is
      if (sd && endEl.value) {
        var s2 = new Date(sd);
        var e2 = new Date(endEl.value);
        if (!isNaN(s2.getTime()) && !isNaN(e2.getTime())) {
          var y = e2.getFullYear() - s2.getFullYear();
          durEl.value = (y >= 0 ? y : 0) + " Years";
        } else {
          durEl.value = "";
        }
      } else {
        durEl.value = "";
      }
    }
    // // Apply revision logic for lease_type_id < 6 and start_date < 2020-01-01
    // var leaseTypeEl = document.getElementById('ltl_lease_type');
    // var leaseTypeId = leaseTypeEl ? parseInt(leaseTypeEl.value) : 0;
    // var revisionPctEl = document.getElementById('ltl_revision_percentage');
    // if (leaseTypeId > 0 && revisionPctEl) {
    //   if (leaseTypeId < 6 && sd && sd < '2020-01-01') {
    //     // Only 50% revision percentage
    //     var revVal = parseFloat(revisionPctEl.value) || 0;
    //     revisionPctEl.value = (revVal > 50 ? 50 : revVal);
    //   } else {
    //     // No revision applicable
    //     revisionPctEl.value = 0;
    //   }
    // }
  }

  function updateFirstLeaseUI() {
    var checkbox = document.getElementById("ltl_is_first_lease");
    var hidden = document.getElementById("ltl_first_lease");
    var lastGroup = document.getElementById("ltl_last_lease_group");
    var lastInput = document.getElementById("ltl_last_lease_annual_value");
    var isFirst = checkbox ? checkbox.checked : true;

    if (hidden) {
      hidden.value = isFirst ? "1" : "0";
    }

    if (lastGroup) {
      if (isFirst) {
        lastGroup.classList.add("d-none");
        if (lastInput) lastInput.removeAttribute("required");
      } else {
        lastGroup.classList.remove("d-none");
        if (lastInput) lastInput.setAttribute("required", "required");
      }
    }
  }

  function validateStartDate() {
    var sel = document.getElementById("ltl_lease_type");
    var opt = sel.options[sel.selectedIndex];
    var eff = opt ? opt.getAttribute("data-effective-from") || "" : "";
    var s = document.getElementById("ltl_start_date").value;
    if (eff && s && s < eff) {
      Swal.fire({
        icon: "warning",
        title: "Invalid Start Date",
        text: "Start date must be on or after " + eff,
      });
      document.getElementById("ltl_start_date").value = eff;
      return false;
    }
    return true;
  }

  function fetchLeaseNumber() {
    fetch("ltl_ajax/generate_lease_number.php")
      .then(function (r) {
        return r.json();
      })
      .then(function (resp) {
        if (resp && resp.success) {
          if (resp.lease_number)
            document.getElementById("ltl_lease_number").value =
              resp.lease_number;
          if (resp.file_number) {
            document.getElementById("ltl_file_number").value = resp.file_number;
            // After initial generation, inject LS+3-letter lease type code if selected
            try {
              var sel = document.getElementById("ltl_lease_type");
              if (sel && sel.value) {
                var opt =
                  sel.options[sel.selectedIndex] ||
                  sel.querySelector(
                    'option[value="' + CSS.escape(sel.value) + '"]'
                  );
                if (opt) {
                  updateFileNumberWithLeaseType(opt);
                }
              }
            } catch (e) {}
          }
        }
      })
      .catch(function () {});
  }

  function disableForm(disabled) {
    var form = document.getElementById("ltlCreateLeaseForm");
    Array.prototype.forEach.call(
      form.querySelectorAll("input, select, textarea, button"),
      function (el) {
        if (el.id === "ltl_edit_btn") return;
        if (el.id === "ltl_create_btn") return;
        el.disabled = !!disabled;
      }
    );
  }

  function onSubmit(e) {
    e.preventDefault();
    if (!validateStartDate()) return;
    var form = document.getElementById("ltlCreateLeaseForm");
    var btn = document.getElementById("ltl_create_btn");
    var leaseIdEl = document.getElementById("ltl_lease_id");
    var isUpdate = !!(leaseIdEl && leaseIdEl.value);
    btn.disabled = true;
    btn.textContent = isUpdate ? "Updating..." : "Saving...";
    Swal.fire({
      title: isUpdate ? "Updating Lease" : "Creating Lease",
      text: "Please wait...",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });
    var fd = new URLSearchParams(new FormData(form));
    var url = isUpdate
      ? "ltl_ajax/update_lease.php"
      : "ltl_ajax/create_lease.php";
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: fd.toString(),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (resp) {
        Swal.close();
        if (resp && resp.success) {
          Swal.fire({
            title: "Success",
            text:
              resp.message || (isUpdate ? "Lease updated" : "Lease created"),
            icon: "success",
          });
          disableForm(true);
          document.getElementById("ltl_create_btn").classList.add("d-none");
          document.getElementById("ltl_edit_btn").classList.remove("d-none");
          // If newly created, set lease_id so subsequent behavior is edit-mode
          if (!isUpdate && resp.lease_id) {
            var hid = document.createElement("input");
            hid.type = "hidden";
            hid.name = "lease_id";
            hid.id = "ltl_lease_id";
            hid.value = String(resp.lease_id);
            form.appendChild(hid);
          }
        } else {
          Swal.fire(
            "Error",
            (resp && resp.message) || "Failed to create lease",
            "error"
          );
        }
      })
      .catch(function () {
        Swal.close();
        Swal.fire("Error", "Server error", "error");
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = "Create Lease & Generate Schedule";
      });
  }

  async function onEdit() {
    // Check for active records before enabling edit
    var leaseIdEl = document.getElementById("ltl_lease_id");
    var lease_id = leaseIdEl ? leaseIdEl.value : null;
    if (!lease_id) {
      disableForm(false);
      return;
    }

    // AJAX check for active records in lease_payments, ltl_write_off, ltl_premium_change
    let resp = await fetch(
      "ltl_ajax/check_active_records.php?lease_id=" +
        encodeURIComponent(lease_id)
    );
    let data = await resp.json();
    if (data && data.has_active) {
      await Swal.fire({
        icon: "warning",
        title: "Active Financial Records",
        html: "Active records exist in <b>lease_payments</b>, <b>ltl_write_off</b>, or <b>ltl_premium_change</b>.<br>This transaction will continue and affect the schedule.",
        confirmButtonText: "Continue Edit",
        showCancelButton: true,
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (!result.isConfirmed) return;
        enableEditFields();
      });
    } else {
      enableEditFields();
    }
  }

  function enableEditFields() {
    // Enable all fields except the listed ones
    var form = document.getElementById("ltlCreateLeaseForm");
    // Keep only truly immutable fields in the exclusion list. Removed lease_type, project names and land_id so they will post.
    var exclude = [
      // "ltl_initial_rent",
      "ltl_revision_percentage",
      "ltl_revision_period",
      "ltl_penalty_rate",
      "ltl_land_address",
      "ltl_lessee_name",
      "ltl_lessee_id",
      "ltl_lessee_name",
      "ltl_land_address",
      "ltl_lease_type",
      "ltl_type_of_project",
      "ltl_lessee_input",
      "ltl_lease_type_id",
    ];
    Array.prototype.forEach.call(
      form.querySelectorAll("input, select, textarea, button"),
      function (el) {
        if (el.id === "ltl_edit_btn") return;
        if (el.id === "ltl_create_btn") return;
        if (exclude.indexOf(el.id) === -1) {
          el.removeAttribute("readonly");
          // If this is a select enhanced with Select2, re-enable it via jQuery to restore the UI.
          try {
            if (window.jQuery && el.tagName === "SELECT" && jQuery.fn.select2) {
              jQuery(el).prop("disabled", false).trigger("change.select2");
            } else {
              el.disabled = false;
            }
          } catch (e) {}
        } else {
          // keep excluded fields readonly, but allow revision values to submit
          el.setAttribute("readonly", "readonly");
          if (
            el.id === "ltl_revision_percentage" ||
            el.id === "ltl_revision_period"
          ) {
            el.disabled = false;
          } else {
            el.disabled = true;
          }
          try {
            if (window.jQuery && el.tagName === "SELECT" && jQuery.fn.select2)
              jQuery(el).prop("disabled", true).trigger("change.select2");
          } catch (e) {}
        }
      }
    );
    var btn = document.getElementById("ltl_create_btn");
    if (btn) {
      btn.textContent = "Update Lease & Recalculate Schedule";
      btn.classList.remove("d-none");
    }
    document.getElementById("ltl_edit_btn").classList.add("d-none");
    // Always load revision percentage from lease table value
    var revPctEl = document.getElementById("ltl_revision_percentage");
    if (revPctEl && revPctEl.hasAttribute("value")) {
      revPctEl.value = revPctEl.getAttribute("value");
    }
    // Apply economy rate logic on edit
    if (typeof applyRateByValuation === "function") {
      applyRateByValuation();
    }
  }

  // Inject or update "LS" + first 3 letters of lease type before the year segment in file number
  function updateFileNumberWithLeaseType(opt) {
    var fileEl = document.getElementById("ltl_file_number");
    if (!fileEl) return;
    var current = (fileEl.value || "").trim();
    if (!current) return;

    var fullText = ((opt && (opt.text || opt.textContent || "")) || "").trim();
    if (!fullText) return;
    var nameOnly = fullText.split("(")[0].trim();
    var letters = nameOnly.replace(/[^A-Za-z]/g, "").toUpperCase();
    if (!letters) return;
    var code = "LS" + letters.slice(0, 3);

    // Split by '/'; find 4-digit year segment
    var parts = current.split("/");
    var yearIdx = -1;
    for (var i = 0; i < parts.length; i++) {
      if (/^\d{4}$/.test(parts[i])) {
        yearIdx = i;
        break;
      }
    }

    if (yearIdx >= 0) {
      var lsIdx = yearIdx - 1;
      if (lsIdx >= 0 && /^LS[A-Z]*$/.test(parts[lsIdx])) {
        parts[lsIdx] = code; // replace existing LS segment
      } else {
        parts.splice(yearIdx, 0, code); // insert before year
      }
      fileEl.value = parts.join("/");
    } else {
      // No explicit year segment; append code near the end
      if (parts.length) {
        // Avoid duplicating if last already LS*
        if (!/^LS[A-Z]*$/.test(parts[parts.length - 1])) {
          parts.push(code);
        } else {
          parts[parts.length - 1] = code;
        }
        fileEl.value = parts.join("/");
      } else {
        fileEl.value = code;
      }
    }
  }

  function init() {
    if (window.jQuery && jQuery.fn.select2) {
      jQuery(".select2").select2({ width: "100%" });
    }
    var leaseIdEl = document.getElementById("ltl_lease_id");
    var hasExisting = !!(leaseIdEl && leaseIdEl.value);
    // Always run applyRateByValuation on Valuation Amount input, both create and edit
    document
      .getElementById("ltl_valuation_amount")
      .addEventListener("input", applyRateByValuation);
    document
      .getElementById("ltl_valuation_amount")
      .addEventListener("input", calculateInitialRent);
    document
      .getElementById("ltl_annual_rent_percentage")
      .addEventListener("input", calculateInitialRent);
    document
      .getElementById("ltl_start_date")
      .addEventListener("change", function () {
        validateStartDate();
        calculateEndDate();
        calculateInitialRent();
      });
    document
      .getElementById("ltl_duration_years")
      .addEventListener("input", function () {
        calculateEndDate();
      });
    var firstLeaseToggle = document.getElementById("ltl_is_first_lease");
    if (firstLeaseToggle) {
      firstLeaseToggle.addEventListener("change", updateFirstLeaseUI);
      updateFirstLeaseUI();
    }
    var endInput = document.getElementById("ltl_end_date");
    if (endInput) {
      endInput.addEventListener("change", function () {
        calculateEndDate();
      });
    }

    function onLeaseTypeChangeNative(ev) {
      var sel =
        ev && ev.target ? ev.target : document.getElementById("ltl_lease_type");
      var opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
      if (!opt && sel && sel.value) {
        // fallback: find by value (works with select2 edge-cases)
        opt = sel.querySelector(
          'option[value="' + CSS.escape(sel.value) + '"]'
        );
      }
      if (!opt || !opt.value) {
        document.getElementById("ltl_annual_rent_percentage").value = "";
        document.getElementById("ltl_duration_years").value = "";
        document.getElementById("ltl_revision_period").value = "";
        document.getElementById("ltl_revision_percentage").value = "";
        document.getElementById("ltl_penalty_rate").value = "";
        document.getElementById("ltl_type_of_project").value = "";
        document.getElementById("ltl_name_of_the_project").value = "";
        document.getElementById("ltl_start_date").removeAttribute("min");
        calculateEndDate();
        calculateInitialRent();
        return;
      }
      // Only autofill from lease master if not editing an existing lease
      var leaseIdEl = document.getElementById("ltl_lease_id");
      var hasExisting = !!(leaseIdEl && leaseIdEl.value);
      var basePctAttr = opt.getAttribute("data-base-rent-percent");
      var durAttr = opt.getAttribute("data-duration-years");
      var revPeriodAttr = opt.getAttribute("data-revision-interval");
      var revPctAttr = opt.getAttribute("data-revision-increase-percent");
      var penRateAttr = opt.getAttribute("data-penalty-rate");
      var eff = opt.getAttribute("data-effective-from") || "";
      var purpose = (opt.getAttribute("data-purpose") || "").trim();

      if (!hasExisting) {
        if (basePctAttr !== null)
          document.getElementById("ltl_annual_rent_percentage").value =
            basePctAttr;
        if (durAttr !== null)
          document.getElementById("ltl_duration_years").value = durAttr;
        if (revPeriodAttr !== null)
          document.getElementById("ltl_revision_period").value = revPeriodAttr;
        // Always fill revision percentage from lease_master
        if (revPctAttr !== null)
          document.getElementById("ltl_revision_percentage").value = revPctAttr;
        if (penRateAttr !== null)
          document.getElementById("ltl_penalty_rate").value = penRateAttr;
        var projectType = "";
        if (purpose) {
          var parts = purpose
            .split(",")
            .map(function (s) {
              return s.trim();
            })
            .filter(Boolean);
          if (parts.length) projectType = parts[0];
        }
        document.getElementById("ltl_type_of_project").value = projectType;
      } else {
        // Always show revision percentage from lease table after creation
        var revPctEl = document.getElementById("ltl_revision_percentage");
        if (revPctEl && revPctEl.hasAttribute("value")) {
          revPctEl.value = revPctEl.getAttribute("value");
        }
      }

      if (eff) {
        document.getElementById("ltl_start_date").setAttribute("min", eff);
        var cur = document.getElementById("ltl_start_date").value;
        if (cur && cur < eff)
          document.getElementById("ltl_start_date").value = eff;
      } else {
        document.getElementById("ltl_start_date").removeAttribute("min");
      }

      calculateEndDate();
      calculateInitialRent();
      applyRateByValuationLocal();
      // Update File Number with LS + first 3 letters of lease type
      updateFileNumberWithLeaseType(opt);
    }

    var leaseTypeEl = document.getElementById("ltl_lease_type");
    leaseTypeEl.addEventListener("change", onLeaseTypeChangeNative);
    // Also bind jQuery/select2 events if available
    if (window.jQuery) {
      jQuery(leaseTypeEl).on("change select2:select", function () {
        onLeaseTypeChangeNative({ target: leaseTypeEl });
      });
    }

    document
      .getElementById("ltlCreateLeaseForm")
      .addEventListener("submit", onSubmit);
    document.getElementById("ltl_edit_btn").addEventListener("click", onEdit);

    // init
    calculateEndDate();
    calculateInitialRent();
    applyRateByValuationLocal();
    if (!hasExisting) {
      fetchLeaseNumber();
    }
    // If a lease type is preselected, trigger autofill once
    var ltSel = document.getElementById("ltl_lease_type");
    if (ltSel) {
      if (ltSel.value) {
        onLeaseTypeChangeNative({ target: ltSel });
        applyRateByValuationLocal();
      }
    }

    // If we already have a lease, keep form disabled and show Edit
    if (hasExisting) {
      // Disable all inputs on load (active lease available before edit)
      var form = document.getElementById("ltlCreateLeaseForm");
      Array.prototype.forEach.call(
        form.querySelectorAll("input, select, textarea, button"),
        function (el) {
          if (el.id === "ltl_edit_btn") return;
          if (el.id === "ltl_create_btn") return;
          el.disabled = true;
          el.setAttribute("readonly", "readonly");
        }
      );
      var createBtn = document.getElementById("ltl_create_btn");
      var editBtn = document.getElementById("ltl_edit_btn");
      if (createBtn) {
        createBtn.textContent = "Update Lease & Recalculate Schedule";
        createBtn.classList.add("d-none");
      }
      if (editBtn) editBtn.classList.remove("d-none");
    }
  }

  window.LTLCreateLease = { init: init };
  // Helper: lock fields if payments exist
  // Only enable specified fields for editing after Active Financial Records alert confirmation
  function applyPaymentsReadonly() {
    [
      "ltl_valuation_amount",
      "ltl_valuation_date",
      "ltl_annual_rent_percentage",
      "ltl_start_date",
      "ltl_file_number",
      "ltl_lease_number",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.removeAttribute("readonly");
        el.removeAttribute("disabled");
      }
    });
  }
})();

// Economy vs Base rate selection based on valuation threshold
function applyRateByValuation() {
  var leaseIdEl = document.getElementById("ltl_lease_id");
  if (leaseIdEl && leaseIdEl.value) return; // 🚫 STOP IN EDIT MODE

  try {
    var valuationEl = document.getElementById("ltl_valuation_amount");
    var leaseTypeEl = document.getElementById("ltl_lease_type");
    var pctEl = document.getElementById("ltl_annual_rent_percentage");
    if (!valuationEl || !leaseTypeEl || !pctEl) return;
    var valuation = parseFloat(valuationEl.value || "");
    if (!(valuation > 0)) return; // no valuation yet
    var opt = leaseTypeEl.options[leaseTypeEl.selectedIndex];
    if (!opt || !opt.value) return;
    var ecoVal = parseFloat(opt.getAttribute("data-economy-valuvation") || "");
    var ecoRate = parseFloat(opt.getAttribute("data-economy-rate") || "");
    var basePct = parseFloat(opt.getAttribute("data-base-rent-percent") || "");
    // Always apply economy rate logic for both create and edit
    if (ecoVal > 0 && ecoRate > 0) {
      if (valuation <= ecoVal) {
        pctEl.value = ecoRate;
      } else if (basePct > 0) {
        pctEl.value = basePct;
      }
    } else if (basePct > 0) {
      pctEl.value = basePct;
    }
    // Refresh initial rent display if function exists
    if (typeof calculateInitialRent === "function") {
      calculateInitialRent();
    }
  } catch (e) {
    /* silent */
  }
}
