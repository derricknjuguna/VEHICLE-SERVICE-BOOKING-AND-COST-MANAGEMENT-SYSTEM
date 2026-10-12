#!/usr/bin/env python3
"""Re-capture scrolled screenshots so services + totals are visible."""
import os, time
from selenium import webdriver
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

BASE = "http://127.0.0.1:8000"
OUT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "screenshots"))

opts = Options()
opts.add_argument("--headless")
opts.add_argument("--width=1280")
opts.add_argument("--height=1000")
svc = Service("/snap/bin/geckodriver")
driver = webdriver.Firefox(service=svc, options=opts)
driver.set_page_load_timeout(30)

def shot(name):
    path = os.path.join(OUT, name)
    driver.save_screenshot(path)
    print("saved", path, os.path.getsize(path))

try:
    # 02: scroll services into view
    driver.get(BASE + "/service.html")
    time.sleep(1)
    driver.execute_script(
        "document.querySelectorAll('fieldset')[2].scrollIntoView({block:'start'});")
    time.sleep(0.8)
    shot("02-service-form.png")

    # 04: fill + select, scroll summary into view
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
      const d=new Date(); d.setDate(d.getDate()+7);
      document.getElementById('service_date').value=d.toISOString().slice(0,10);
      for (const id of ['svc_engine_oil','svc_brake','svc_pickup']) {
        const b=document.getElementById(id);
        if(!b.checked){ b.click(); }
      }
      document.querySelector('.summary').scrollIntoView({block:'center'});
    """)
    time.sleep(1)
    txt = driver.execute_script(
        "return document.getElementById('estimatedTotal').textContent + '|' +"
        " document.getElementById('serviceCount').textContent;")
    print("live summary:", txt)
    shot("04-dynamic-cost-calculation.png")

    # 05: submit then scroll to total row
    driver.execute_script("document.getElementById('bookingForm').submit();")
    time.sleep(2)
    driver.execute_script(
        "document.querySelector('.total-row').scrollIntoView({block:'center'});")
    time.sleep(0.8)
    shot("05-booking-confirmation.png")
finally:
    driver.quit()
print("done")
