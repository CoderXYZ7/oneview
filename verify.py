import requests
import re

BASE_URL = "http://localhost:8000"
ADMIN_URL = f"{BASE_URL}/admin"
LOGIN_URL = f"{ADMIN_URL}/login.php"
UPLOAD_URL = f"{ADMIN_URL}/upload.php"
INDEX_URL = f"{ADMIN_URL}/index.php"

session = requests.Session()

def login():
    print("Logging in...")
    resp = session.post(LOGIN_URL, data={'username': 'admin', 'password': 'password123'})
    if resp.url != f"{ADMIN_URL}/index.php":
        print(f"Login failed. Redirected to {resp.url}")
        exit(1)
    print("Login successful.")

def upload_file():
    print("Uploading file...")
    files = {'file': ('test.txt', b'Hello World', 'text/plain')}
    data = {'lock_on_access': '1', 'max_minutes': '1'}
    resp = session.post(UPLOAD_URL, files=files, data=data)
    if resp.status_code != 200:
        print("Upload failed.")
        exit(1)
    
    # Extract ID from dashboard
    resp = session.get(INDEX_URL)
    # Match link: <a href="../index.php?id=..."
    match = re.search(r'href="\.\./index\.php\?id=([a-f0-9]+)"', resp.text)
    if not match:
        print("Could not find file ID in dashboard.")
        # print(resp.text)
        exit(1)
    
    file_id = match.group(1)
    print(f"File uploaded. ID: {file_id}")
    return file_id

def access_file(file_id, expect_success=True):
    print(f"Accessing file {file_id} (Expected success: {expect_success})...")
    url = f"{BASE_URL}/index.php?id={file_id}&action=stream"
    resp = requests.get(url)
    
    if expect_success:
        if resp.status_code == 200 and resp.text == "Hello World":
            print("Success: File content verified.")
        else:
            print(f"FAIL: Expected 200, got {resp.status_code}")
            exit(1)
    else:
        if resp.status_code == 403:
            print("Success: Access denied as expected.")
        else:
            print(f"FAIL: Expected 403, got {resp.status_code}")
            exit(1)

def unlock_file(file_id):
    print(f"Unlocking file {file_id}...")
    # Get unlock link from dashboard
    resp = session.get(INDEX_URL)
    # Look for unlock link
    unlock_url = f"{INDEX_URL}?unlock={file_id}"
    session.get(unlock_url)
    print("Unlock request sent.")

def run():
    login()
    file_id = upload_file()
    
    print("\n--- Test 1: First Access ---")
    access_file(file_id, expect_success=True)
    
    print("\n--- Test 2: Second Access (Should be locked) ---")
    access_file(file_id, expect_success=False)
    
    print("\n--- Test 3: Unlock and Access ---")
    unlock_file(file_id)
    access_file(file_id, expect_success=True)
    
    print("\n--- Test 4: Second Access after Unlock (Should be locked again) ---")
    access_file(file_id, expect_success=False)
    
    print("\nALL TESTS PASSED")

if __name__ == "__main__":
    run()
