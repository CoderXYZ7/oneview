import requests
import re
import os
import time

BASE_URL = "http://localhost:8000"
ADMIN_URL = f"{BASE_URL}/admin"
LOGIN_URL = f"{ADMIN_URL}/login.php"
UPLOAD_URL = f"{ADMIN_URL}/upload.php"
INDEX_URL = f"{ADMIN_URL}/index.php"
LOG_FILE = "private/app.log"

session = requests.Session()

def login():
    session.post(LOGIN_URL, data={'username': 'admin', 'password': 'password123'})

def upload_file(filename, content):
    print(f"Uploading {filename}...")
    files = {'file': (filename, content, 'text/plain')}
    data = {'max_minutes': '0', 'allow_download': '1'}
    session.post(UPLOAD_URL, files=files, data=data)
    
    # Get ID
    matches = re.findall(r'href="edit\.php\?id=([a-f0-9]+)"', session.get(INDEX_URL).text)
    if not matches:
        print("Failed to get ID")
        exit(1)
    return matches[-1]

def delete_file(id):
    print(f"Deleting {id}...")
    session.get(f"{INDEX_URL}?delete={id}")

def check_deleted(id):
    resp = session.get(INDEX_URL)
    if id in resp.text:
        print(f"FAIL: ID {id} still found in dashboard")
        exit(1)
    print("PASS: ID removed from dashboard")
    
    # Check if file is gone from disk (optional, requires checking logs or using another script via php)
    # But good enough to check dashboard.
    
def check_logs(id):
    print("Checking logs...")
    if not os.path.exists(LOG_FILE):
        print(f"FAIL: Log file {LOG_FILE} not found")
        exit(1)
        
    with open(LOG_FILE, 'r') as f:
        logs = f.read()
        
    if f"Deleting {id}" in logs:
        print("PASS: Delete action logged")
    else:
        print("FAIL: Delete action NOT logged")
        print("Logs:\n", logs)
        exit(1)

def run():
    print("Starting Delete Verification")
    if os.path.exists(LOG_FILE):
        # Clear log for clean test
        open(LOG_FILE, 'w').close()
        
    login()
    id = upload_file("delete_test.txt", b"Delete Me")
    print(f"Uploaded {id}")
    
    delete_file(id)
    check_deleted(id)
    
    # Allow some time for disk flush?
    time.sleep(0.1)
    check_logs(id)
    
    print("VERIFICATION COMPLETE")

if __name__ == "__main__":
    run()
