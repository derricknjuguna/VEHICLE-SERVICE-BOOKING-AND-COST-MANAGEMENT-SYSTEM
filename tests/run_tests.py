#!/usr/bin/env python3
"""End-to-end HTTP tests for AutoCare Garage (assignment TESTS 1-20 over HTTP).

Starts nothing by itself: run against a PHP dev server, e.g.
    php -S localhost:8000
    python3 tests/run_tests.py --base http://localhost:8000

Checks:
- pages serve (index, service form, css, js, php GET handling)
- all 6 official prices appear consistently
- valid booking returns SERVICE REQUEST RECEIVED with correct total
- invalid bookings are rejected (not shown as successful)
- tampered totals do not change the authoritative total
- HTML in input is escaped in the response
"""
import argparse, re, sys, urllib.parse, urllib.request, urllib.error

PRICES = {
    "engine_oil": 2000, "brake_inspection": 3000, "wheel_alignment": 3500,
    "computer_diagnosis": 2500, "full_service": 8000, "pickup_delivery": 1500,
}
EXPECTED = [
    ("TEST 1", ["engine_oil"], 2000), ("TEST 2", ["brake_inspection"], 3000),
    ("TEST 3", ["wheel_alignment"], 3500), ("TEST 4", ["computer_diagnosis"], 2500),
    ("TEST 5", ["full_service"], 8000), ("TEST 6", ["pickup_delivery"], 1500),
    ("TEST 7", ["engine_oil", "brake_inspection", "pickup_delivery"], 6500),
]

passed = failed = 0
def check(name, cond, detail=""):
    global passed, failed
    if cond: passed += 1; print(f"PASS {name}")
    else: failed += 1; print(f"FAIL {name} {detail}")

def get(base, path):
    with urllib.request.urlopen(base + path, timeout=10) as r:
        return r.status, r.read().decode("utf-8", "replace")

def post(base, data):
    fields = []
    for k, v in data.items():
        if isinstance(v, list):
            for item in v: fields.append((k, item))
        else: fields.append((k, v))
    body = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(base + "/process_service.php", data=body, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=10) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")

def valid(**ov):
    from datetime import date, timedelta
    d = {"customer_name": "Jane Wanjiku", "phone": "0712345678",
         "email": "jane@example.com", "vehicle_reg": "KDA 123B",
         "vehicle_type": "SUV", "vehicle_model": "Toyota RAV4",
         "manufacture_year": "2018",
         "service_date": (date.today() + timedelta(days=7)).isoformat(),
         "services[]": ["engine_oil"]}
    # Allow callers to pass services=[...] (without brackets) for convenience.
    if "services" in ov:
        ov["services[]"] = ov.pop("services")
    d.update(ov)
    return d

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="http://localhost:8000")
    args = ap.parse_args()
    base = args.base.rstrip("/")

    try:
        s, home = get(base, "/index.html")
        check("homepage serves", s == 200 and "Book a Vehicle Service" in home, f"status={s}")
        check("homepage links to service.html", "service.html" in home)
        s, form = get(base, "/service.html")
        check("service form serves", s == 200 and 'name="services[]"' in form, f"status={s}")
        for label in ["Engine Oil Change", "Brake Inspection", "Wheel Alignment",
                      "Computer Diagnosis", "Full Service", "Vehicle Pickup and Delivery"]:
            check(f"form lists {label}", label in form)
        check("form uses POST to process_service.php",
              'method="POST"' in form and 'action="process_service.php"' in form)
        for fn in ["validateServiceForm", "calculateServiceCost", "checkVehicleAge",
                   "updateSelectedServices", "updateServiceCount"]:
            check(f"js defines {fn}()", fn in get(base, "/js/script.js")[1])
        s, js = get(base, "/js/script.js")
        for k, v in PRICES.items():
            check(f"js price {k}={v}", f"{k}" in js and str(v) in js)
        s, css = get(base, "/css/style.css")
        check("css serves", s == 200 and len(css) > 200)
        s, php_get = get(base, "/process_service.php")
        check("PHP GET handled without warnings",
              s == 200 and "Invalid access" in php_get and "Warning" not in php_get
              and "Fatal" not in php_get and "Notice:" not in php_get)

        for name, svcs, total in EXPECTED:
            s, html = post(base, valid(services=svcs))
            want = f"KSh {total:,}"
            check(f"{name} HTTP total {want}",
                  s == 200 and "SERVICE REQUEST RECEIVED" in html and want in html
                  and "Warning" not in html, f"status={s}")

        # TEST 8 is browser deselect behaviour; verify calculator agreement via totals above + js file.
        # TEST 9: missing name
        s, html = post(base, valid(customer_name="   "))
        check("TEST 9: missing name rejected",
              "SERVICE REQUEST RECEIVED" not in html and "Customer name" in html)
        # TEST 10/11: phone lengths
        s, html = post(base, valid(phone="071234567"))
        check("TEST 10: 9-digit phone rejected", "SERVICE REQUEST RECEIVED" not in html)
        s, html = post(base, valid(phone="07123456789"))
        check("TEST 11: 11-digit phone rejected", "SERVICE REQUEST RECEIVED" not in html)
        s, html = post(base, valid(phone="07123ab678"))
        check("TEST 11b: phone with letters rejected", "SERVICE REQUEST RECEIVED" not in html)
        # TEST 12
        s, html = post(base, valid(email="not-an-email"))
        check("TEST 12: invalid email rejected", "SERVICE REQUEST RECEIVED" not in html)
        # TEST 13
        s, html = post(base, {**valid(), "services[]": []})
        # urllib drops empty list; send without services key instead
        fields = valid(); del fields["services[]"]
        s, html = post(base, fields)
        check("TEST 13: no services rejected", "SERVICE REQUEST RECEIVED" not in html)
        # TEST 14
        s, html = post(base, valid(manufacture_year="1899"))
        check("TEST 14: year 1899 rejected", "SERVICE REQUEST RECEIVED" not in html)
        # TEST 15
        from datetime import date, timedelta
        past = (date.today() - timedelta(days=1)).isoformat()
        s, html = post(base, valid(service_date=past))
        check("TEST 15: past date rejected", "SERVICE REQUEST RECEIVED" not in html)
        # TEST 16
        s, html = post(base, valid(services=["free_tuning"]))
        check("TEST 16: unexpected service rejected", "SERVICE REQUEST RECEIVED" not in html)
        # TEST 17
        s, html = post(base, valid(services=["engine_oil", "pickup_delivery"]))
        check("TEST 17: valid booking confirmed",
              s == 200 and "SERVICE REQUEST RECEIVED" in html and "KSh 3,500" in html)
        # TEST 18: tamper attempt
        d = valid(services=["engine_oil"]); d["total"] = "1"; d["estimatedTotal"] = "1"
        s, html = post(base, d)
        check("TEST 18: tampered total ignored (still KSh 2,000)",
              "SERVICE REQUEST RECEIVED" in html and "KSh 2,000" in html)
        # TEST 19: XSS escaped
        s, html = post(base, valid(customer_name='<script>alert(1)</script>'))
        check("TEST 19: HTML escaped",
              "<script>alert(1)</script>" not in html and "&lt;script&gt;" in html)
        # TEST 20
        s, html = post(base, valid(services=["full_service", "pickup_delivery"]))
        check("TEST 20: pickup adds KSh 1,500 (total KSh 9,500)",
              "SERVICE REQUEST RECEIVED" in html and "KSh 9,500" in html
              and "Vehicle Pickup and Delivery" in html)
    except Exception as e:
        print(f"ERROR: {e}")
        return 1

    print(f"\n{passed} passed, {failed} failed.")
    return 1 if failed else 0

if __name__ == "__main__":
    sys.exit(main())
