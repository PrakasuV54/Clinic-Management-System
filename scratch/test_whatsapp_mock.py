import urllib.request
import json
import traceback

payload = {
    "customer_name": "Test Customer Mock",
    "mobile_number": "9876543210",
    "medicines": [],
    "injection_cost": 0,
    "iv_cost": 0,
    "upt_cost": 0,
    "cash_amount": 0,
    "gpay_amount": 0,
    "phonepe_amount": 0,
    "paid_amount": 0,
    "balance_amount": 0,
    "discount_percent": 0,
    "send_whatsapp": True,
    "whatsapp_image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
}

try:
    url = "http://localhost:8005/api/direct_sales/add"
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json"}
    )
    with urllib.request.urlopen(req) as response:
        body = response.read().decode("utf-8")
        print(f"Status Code: {response.getcode()}")
        print("Response Body:")
        print(body)
except Exception as e:
    print(f"Error making request: {e}")
    traceback.print_exc()
