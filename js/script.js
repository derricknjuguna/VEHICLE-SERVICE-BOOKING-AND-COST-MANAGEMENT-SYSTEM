/*
 * AutoCare Garage - booking form behaviour
 *
 * Single pricing policy (authoritative values duplicated in PHP):
 *   Engine Oil Change            KSh 2,000
 *   Brake Inspection             KSh 3,000
 *   Wheel Alignment              KSh 3,500
 *   Computer Diagnosis           KSh 2,500
 *   Full Service                 KSh 8,000
 *   Vehicle Pickup and Delivery  KSh 1,500  (individualization requirement)
 *
 * All amounts are integers (Kenyan shillings) to avoid float errors.
 */

"use strict";

// Service identifiers used as checkbox values. Must match PHP's price list keys.
var SERVICE_PRICES = {
  engine_oil: 2000,
  brake_inspection: 3000,
  wheel_alignment: 3500,
  computer_diagnosis: 2500,
  full_service: 8000,
  pickup_delivery: 1500
};

var SERVICE_LABELS = {
  engine_oil: "Engine Oil Change",
  brake_inspection: "Brake Inspection",
  wheel_alignment: "Wheel Alignment",
  computer_diagnosis: "Computer Diagnosis",
  full_service: "Full Service",
  pickup_delivery: "Vehicle Pickup and Delivery"
};

var VEHICLE_TYPES = ["Saloon Car", "SUV", "Pickup", "Van"];

// Short vehicle-specific guidance shown when the vehicle type changes.
var VEHICLE_INFO = {
  "Saloon Car": "Saloon Car: standard ramps and oils are used. Most services take 1-2 hours.",
  "SUV": "SUV: larger brake and alignment checks apply. Please arrive with a spare tyre accessible.",
  "Pickup": "Pickup: load-bed inspection is included free with Full Service. Heavy-duty oils may apply.",
  "Van": "Van: high-roof bay is reserved automatically. Allow extra time for diagnosis."
};

/* Format an integer KSh amount, e.g. 6500 -> "KSh 6,500". */
function formatKSh(amount) {
  var n = Number(amount) || 0;
  return "KSh " + n.toLocaleString("en-KE");
}

/* Return checkbox elements for services. */
function getServiceCheckboxes() {
  return Array.prototype.slice.call(document.querySelectorAll('input[name="services[]"]'));
}

/* Return the selected service identifiers (only known identifiers). */
function getSelectedServiceIds() {
  return getServiceCheckboxes()
    .filter(function (box) { return box.checked; })
    .map(function (box) { return box.value; });
}

/*
 * calculateServiceCost
 * Calculates the estimate from the currently selected services.
 * When called with an array (used by tests), it totals that array instead
 * of reading the DOM, using the same price map.
 */
function calculateServiceCost(selected) {
  var ids = Array.isArray(selected) ? selected : getSelectedServiceIds();
  var total = 0;
  for (var i = 0; i < ids.length; i++) {
    if (Object.prototype.hasOwnProperty.call(SERVICE_PRICES, ids[i])) {
      total += SERVICE_PRICES[ids[i]];
    }
  }
  var totalEl = (typeof document !== "undefined" && document.getElementById)
    ? document.getElementById("estimatedTotal") : null;
  if (totalEl && !Array.isArray(selected)) {
    totalEl.textContent = formatKSh(total);
  }
  return total;
}

/* Render the live selected-services summary list. */
function updateSelectedServices() {
  var list = document.getElementById("selectedServices");
  if (!list) return;
  var ids = getSelectedServiceIds();
  list.innerHTML = "";
  if (ids.length === 0) {
    var li = document.createElement("li");
    li.className = "empty-note";
    li.textContent = "No services selected yet.";
    list.appendChild(li);
  } else {
    ids.forEach(function (id) {
      var item = document.createElement("li");
      var label = SERVICE_LABELS[id] || id;
      var price = Object.prototype.hasOwnProperty.call(SERVICE_PRICES, id)
        ? formatKSh(SERVICE_PRICES[id]) : "KSh 0";
      item.textContent = label + " — " + price;
      list.appendChild(item);
    });
  }
  // Keep every derived display in agreement.
  updateServiceCount();
  calculateServiceCost();
}

/* Update the live count of selected services. */
function updateServiceCount() {
  var countEl = document.getElementById("serviceCount");
  if (!countEl) return;
  countEl.textContent = String(getSelectedServiceIds().length);
}

/*
 * checkVehicleAge
 * Shows vehicle-specific info for the chosen type and warns when the
 * vehicle is older than 15 years. Called on vehicle-type and year changes.
 * Returns the computed age (or null when the year is not usable).
 */
function checkVehicleAge() {
  var typeEl = document.getElementById("vehicle_type");
  var yearEl = document.getElementById("manufacture_year");
  var infoEl = document.getElementById("vehicleInfo");
  var warnEl = document.getElementById("ageWarning");
  var age = null;

  if (typeEl && infoEl) {
    var t = typeEl.value;
    if (t && VEHICLE_INFO[t]) {
      infoEl.textContent = VEHICLE_INFO[t];
      infoEl.classList.remove("hidden");
    } else {
      infoEl.textContent = "";
      infoEl.classList.add("hidden");
    }
  }

  if (yearEl && warnEl) {
    var year = parseInt(yearEl.value, 10);
    var currentYear = new Date().getFullYear();
    if (!isNaN(year) && year >= 1900 && year <= currentYear) {
      age = currentYear - year;
      if (age > 15) {
        warnEl.textContent = "Note: this vehicle is " + age + " years old. " +
          "Our mechanics recommend adding Computer Diagnosis for older vehicles.";
        warnEl.classList.remove("hidden");
      } else {
        warnEl.textContent = "";
        warnEl.classList.add("hidden");
      }
    } else {
      warnEl.textContent = "";
      warnEl.classList.add("hidden");
    }
  }
  return age;
}

function setFieldError(fieldId, message) {
  var err = document.getElementById(fieldId + "-error");
  var field = document.getElementById(fieldId);
  if (err) err.textContent = message || "";
  if (field) {
    if (message) field.setAttribute("aria-invalid", "true");
    else field.removeAttribute("aria-invalid");
  }
}

/*
 * validateServiceForm
 * Returns true when the form is valid, otherwise shows messages and
 * returns false so the form is not submitted.
 */
function validateServiceForm() {
  var ok = true;
  var firstInvalid = null;

  function fail(fieldId, message) {
    setFieldError(fieldId, message);
    ok = false;
    if (!firstInvalid) {
      firstInvalid = document.getElementById(fieldId);
    }
  }

  var name = document.getElementById("customer_name").value;
  var phone = document.getElementById("phone").value;
  var email = document.getElementById("email").value.trim();
  var reg = document.getElementById("vehicle_reg").value;
  var model = document.getElementById("vehicle_model").value;
  var type = document.getElementById("vehicle_type").value;
  var yearRaw = document.getElementById("manufacture_year").value.trim();
  var dateRaw = document.getElementById("service_date").value;

  // A. Customer name must not be empty / only spaces.
  if (!name || name.trim() === "") fail("customer_name", "Please enter the customer name.");
  else setFieldError("customer_name", "");

  // B. Phone: exactly 10 digits, nothing else. Do not auto-fix invalid input.
  if (!/^[0-9]{10}$/.test(phone)) {
    fail("phone", "Phone number must contain exactly 10 digits (numbers only).");
  } else setFieldError("phone", "");

  // C. Reasonable email format.
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    fail("email", "Please enter a valid email address.");
  } else setFieldError("email", "");

  // D. Registration must not be empty.
  if (!reg || reg.trim() === "") fail("vehicle_reg", "Please enter the vehicle registration number.");
  else setFieldError("vehicle_reg", "");

  // E. Model must not be empty.
  if (!model || model.trim() === "") fail("vehicle_model", "Please enter the vehicle model.");
  else setFieldError("vehicle_model", "");

  // F. Vehicle type must be one of the four permitted values.
  if (VEHICLE_TYPES.indexOf(type) === -1) fail("vehicle_type", "Please choose a valid vehicle type.");
  else setFieldError("vehicle_type", "");

  // G. Year: integer 1900..current year.
  var currentYear = new Date().getFullYear();
  if (!/^-?\d+$/.test(yearRaw)) {
    fail("manufacture_year", "Year must be a whole number between 1900 and " + currentYear + ".");
  } else {
    var year = parseInt(yearRaw, 10);
    if (year < 1900 || year > currentYear) {
      fail("manufacture_year", "Year must be between 1900 and " + currentYear + ".");
    } else setFieldError("manufacture_year", "");
  }

  // H. Preferred date: valid and not in the past (compared by calendar day).
  if (!dateRaw) {
    fail("service_date", "Please choose a preferred service date.");
  } else {
    var chosen = new Date(dateRaw + "T00:00:00");
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    if (isNaN(chosen.getTime())) {
      fail("service_date", "Please choose a valid service date.");
    } else if (chosen < today) {
      fail("service_date", "Preferred service date must not be in the past.");
    } else setFieldError("service_date", "");
  }

  // I + J. At least one valid service; reject unexpected identifiers.
  var checked = getServiceCheckboxes().filter(function (b) { return b.checked; });
  var servicesError = "";
  if (checked.length === 0) {
    servicesError = "Please select at least one service.";
  } else {
    for (var i = 0; i < checked.length; i++) {
      if (!Object.prototype.hasOwnProperty.call(SERVICE_PRICES, checked[i].value)) {
        servicesError = "One or more selected services are invalid. Please re-select.";
        break;
      }
    }
  }
  var servicesErrEl = document.getElementById("services-error");
  if (servicesErrEl) servicesErrEl.textContent = servicesError;
  if (servicesError) {
    ok = false;
    if (!firstInvalid && checked.length > 0) firstInvalid = checked[0];
  }

  var alertBox = document.getElementById("formAlert");
  if (alertBox) {
    if (!ok) {
      alertBox.textContent = "Please correct the highlighted fields before submitting.";
      alertBox.classList.add("show", "error");
    } else {
      alertBox.textContent = "";
      alertBox.classList.remove("show", "error");
    }
  }

  if (!ok && firstInvalid && firstInvalid.focus) firstInvalid.focus();
  return ok;
}

/* Wire up events once the DOM is ready. Single listeners only. */
function initBookingForm() {
  var form = document.getElementById("bookingForm");
  if (!form) return;

  // change: service checkboxes + vehicle type select.
  form.addEventListener("change", function (event) {
    var target = event.target;
    if (target && target.name === "services[]") {
      updateSelectedServices();
    }
    if (target && (target.id === "vehicle_type" || target.id === "manufacture_year")) {
      checkVehicleAge();
    }
  });

  // input: live age warning while typing the year; clear alert while fixing.
  form.addEventListener("input", function (event) {
    var target = event.target;
    if (target && target.id === "manufacture_year") checkVehicleAge();
  });

  // click: reset button also refreshes the live summary.
  var resetBtn = document.getElementById("resetBtn");
  if (resetBtn) {
    resetBtn.addEventListener("click", function () {
      // Let the native reset happen first, then refresh.
      setTimeout(function () {
        ["customer_name", "phone", "email", "vehicle_reg", "vehicle_model",
         "vehicle_type", "manufacture_year", "service_date"].forEach(function (id) {
          setFieldError(id, "");
        });
        var sErr = document.getElementById("services-error");
        if (sErr) sErr.textContent = "";
        var alertBox = document.getElementById("formAlert");
        if (alertBox) alertBox.classList.remove("show", "error");
        updateSelectedServices();
        checkVehicleAge();
        var btn = document.getElementById("submitBtn");
        if (btn) { btn.disabled = false; btn.textContent = "Submit Booking"; }
      }, 0);
    });
  }

  // submit: validate; prevent double submission while valid request proceeds.
  form.addEventListener("submit", function (event) {
    if (!validateServiceForm()) {
      event.preventDefault();
      return;
    }
    var btn = document.getElementById("submitBtn");
    if (btn) {
      if (btn.disabled) {
        event.preventDefault();
        return;
      }
      btn.disabled = true;
      btn.textContent = "Submitting…";
    }
  });

  updateSelectedServices();
  checkVehicleAge();
}

if (typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", initBookingForm);
}

// Expose for Node-based tests.
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    SERVICE_PRICES: SERVICE_PRICES,
    SERVICE_LABELS: SERVICE_LABELS,
    VEHICLE_TYPES: VEHICLE_TYPES,
    formatKSh: formatKSh,
    calculateServiceCost: calculateServiceCost,
    validateServiceForm: validateServiceForm,
    checkVehicleAge: checkVehicleAge,
    updateSelectedServices: updateSelectedServices,
    updateServiceCount: updateServiceCount
  };
}
