// Node tests for the JavaScript price calculator (TESTS 1-8, 20 logic).
// Run: node tests/test_js_pricing.js
var mod = require("../js/script.js");

var cases = [
  { name: "TEST 1: Engine Oil Change only", selected: ["engine_oil"], expected: 2000 },
  { name: "TEST 2: Brake Inspection only", selected: ["brake_inspection"], expected: 3000 },
  { name: "TEST 3: Wheel Alignment only", selected: ["wheel_alignment"], expected: 3500 },
  { name: "TEST 4: Computer Diagnosis only", selected: ["computer_diagnosis"], expected: 2500 },
  { name: "TEST 5: Full Service only", selected: ["full_service"], expected: 8000 },
  { name: "TEST 6: Vehicle Pickup and Delivery only", selected: ["pickup_delivery"], expected: 1500 },
  { name: "TEST 7: Engine Oil + Brake + Pickup", selected: ["engine_oil", "brake_inspection", "pickup_delivery"], expected: 6500 },
  { name: "TEST 8: deselect decreases total", selected: ["engine_oil", "brake_inspection"], expected: 5000 },
  { name: "TEST 8b: after deselect one", selected: ["engine_oil"], expected: 2000 },
  { name: "TEST 20 logic: Full Service + Pickup", selected: ["full_service", "pickup_delivery"], expected: 9500 }
];

var priceMap = { engine_oil: 2000, brake_inspection: 3000, wheel_alignment: 3500, computer_diagnosis: 2500, full_service: 8000, pickup_delivery: 1500 };
var failures = 0;

Object.keys(priceMap).forEach(function (k) {
  if (mod.SERVICE_PRICES[k] !== priceMap[k]) {
    console.log("FAIL price map " + k + ": got " + mod.SERVICE_PRICES[k] + " expected " + priceMap[k]);
    failures++;
  }
});
if (typeof mod.calculateServiceCost !== "function") { console.log("FAIL missing calculateServiceCost"); failures++; }
if (typeof mod.validateServiceForm !== "function") { console.log("FAIL missing validateServiceForm"); failures++; }
if (typeof mod.checkVehicleAge !== "function") { console.log("FAIL missing checkVehicleAge"); failures++; }
if (typeof mod.updateSelectedServices !== "function") { console.log("FAIL missing updateSelectedServices"); failures++; }
if (typeof mod.updateServiceCount !== "function") { console.log("FAIL missing updateServiceCount"); failures++; }

cases.forEach(function (c) {
  var got = mod.calculateServiceCost(c.selected);
  if (got === c.expected) console.log("PASS " + c.name + " => " + got);
  else { console.log("FAIL " + c.name + ": got " + got + " expected " + c.expected); failures++; }
});

if (mod.formatKSh(6500) !== "KSh 6,500") { console.log("FAIL formatKSh(6500) => " + mod.formatKSh(6500)); failures++; }
else console.log("PASS formatKSh => KSh 6,500");

if (failures > 0) { console.log(failures + " FAILURE(S)"); process.exit(1); }
console.log("All JS pricing tests passed.");
