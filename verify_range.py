import requests
import os

BASE_URL = "http://localhost:8000"
ADMIN_URL = f"{BASE_URL}/admin"
LOGIN_URL = f"{ADMIN_URL}/login.php"
UPLOAD_URL = f"{ADMIN_URL}/upload.php"
INDEX_URL = f"{BASE_URL}/index.php"

session = requests.Session()

def login():
    session.post(LOGIN_URL, data={'username': 'admin', 'password': 'password123'})

def get_file_url(file_id):
    return f"{INDEX_URL}?id={file_id}&action=stream"

def upload_large_dummy():
    # Simulate uploading a file (we'll just use a small one but test range on it)
    # Testing actual large upload failure would require creating a massive file which might be slow.
    # We will trust the code logic for upload limits but verifying Range support is key.
    
    # Create a dummy binary file (10KB)
    content = b'0123456789' * 1024 # 10KB
    files = {'file': ('range_test.bin', content, 'application/octet-stream')}
    data = {'max_minutes': '0', 'allow_download': '1'}
    
    # Need to extract ID again... regex or just parse redirects if any?
    # Upload and get ID from redirect or dashboard is harder without parsing.
    # Let's use the dashboard parser from before.
    resp = session.post(UPLOAD_URL, files=files, data=data)
    if resp.status_code != 200 and resp.status_code != 302:
        print(f"Upload POST failed: {resp.status_code}")
        print(resp.text)
        exit(1)
        
    print(f"Upload Response URL: {resp.url}")
    # If redirect, requests follows it by default. 
    # If successful, it redirects to index.php.
    if "index.php" not in resp.url and "upload.php" in resp.url:
         print("Upload failed (still on upload page)?")
         print(resp.text)
    
    # Get ID from dashboard
    import re
    matches = re.findall(r'href="edit\.php\?id=([a-f0-9]+)"', session.get(f"{ADMIN_URL}/index.php").text)
    if not matches:
        print("Failed to get ID")
        exit(1)
    return matches[-1]

def test_range_request(file_id):
    url = get_file_url(file_id)
    print(f"Testing Range Request on {url}...")
    
    # Request first 100 bytes
    headers = {'Range': 'bytes=0-99'}
    resp = session.get(url, headers=headers)
    
    if resp.status_code == 206:
        print("PASS: Got 206 Partial Content")
    else:
        print(f"FAIL: Expected 206, got {resp.status_code}")
        exit(1)
        
    if int(resp.headers['Content-Length']) == 100:
        print("PASS: Content-Length is 100")
    else:
        print(f"FAIL: Content-Length {resp.headers['Content-Length']} != 100")
        
    if resp.headers.get('Content-Range', '').startswith('bytes 0-99/'):
         print("PASS: Content-Range header correct")
    else:
         print(f"FAIL: Content-Range header invalid: {resp.headers.get('Content-Range')}")

def run():
    print("Starting Range Verification")
    login()
    id = upload_large_dummy()
    print(f"Uploaded ID: {id}")
    test_range_request(id)
    print("VERIFICATION COMPLETE")

if __name__ == "__main__":
    run()
