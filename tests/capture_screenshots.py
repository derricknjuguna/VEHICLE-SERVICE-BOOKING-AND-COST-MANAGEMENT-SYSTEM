#!/usr/bin/env python3
"""Capture genuine screenshots of the running app via Selenium + headless Chromium."""
import os, time
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By

BASE = "http://127.0.0.1:8000"
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "screenshots")
os.makedirs(OUT, exist_ok=True)

opts = Options()
opts.binary_location = "/snap/bin/chromium"
opts.add_argument("--headless=new")
opts.add_argument("--no-sandbox")
opts.add_argument("--disable-gpu")
opts.add_argument("--disable-dev-shm-usage")
opts.add_argument("--window-size=1280,1000")
svc = Service("/snap/chromium/3551/usr/lib/chromium-browser/chromedriver")
driver = webdriver.Chrome(service=svc, options=opts)
driver.set_page_load_timeout(20)

def shot(name):
    path = os.path.join(OUT, name)
    driver.save_screenshot(path)
    print("saved", path, os.path.getsize(path), "bytes")

try:
    # 01 homepage
    driver.get(BASE + "/index.html")
    time.sleep(1)
    shot("01-homepage.png")

    # 02 service form (all services visible)
    driver.get(BASE + "/service.html")
    time.sleep(1)
    shot("02-service-form.png")

    # 03 validation: submit empty form to trigger JS validation errors
    driver.get(BASE + "/service.html")
    time.sleep(1)
    driver.execute_script("document.getElementById('bookingForm').requestSubmit();")
    time.sleep(1)
    # also fill a bad phone to show a specific error
    driver.execute_script("document.getElementById('phone').value='123';"
                          "document.getElementById('bookingForm').requestSubmit();")
    time.sleep(1)
    shot("03-form-validation.png")

    # 04 dynamic cost: select oil + brake + pickup, set vehicle info
    driver.get(BASE + "/service.html")
    time.sleep(1)
    driver.execute_script("""
      document.getElementById('customer_name').value='Jane Wanjiku';
      document.getElementById('phone').value='0712345678';
      document.getElementById('email').value='jane@example.com';
      document.getElementById('vehicle_reg').value='KDA 123B';
      document.getElementById('vehicle_type').value='SUV';
      document.getElementById('vehicle_type').dispatchEvent(new Event('change',{bubbles:true}));
      document.getElementById('vehicle_model').value='Toyota RAV4';
      document.getElementById('manufacture_year').value='2018';
      document.getElementById('manufacture_year').dispatchEvent(new Event('input',{bubbles:true}));
      for (const id of ['svc_engine_oil','svc_brake','svc_pickup']) {
        const b=document.getElementById(id);
        if(!b.checked){ b.click(); }
      }
    """)
    time.sleep(1)
    shot("04-dynamic-cost-calculation.png")

    # 05 confirmation: submit a valid booking via the real form
    driver.execute_script("""
      const d=new Date(); d.setDate(d.getDate()+7);
      document.getElementById('service_date').value=d.toISOString().slice(0,10);
    """)
    time.sleep(0.5)
    driver.execute_script("document.getElementById('bookingForm').submit();")
    time.sleep(2)
    shot("05-booking-confirmation.png")
    print("current url:", driver.current_url)
finally:
    driver.quit()
print("done")
