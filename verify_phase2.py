import requests
import re

BASE_URL = "http://localhost:8000"
ADMIN_URL = f"{BASE_URL}/admin"
LOGIN_URL = f"{ADMIN_URL}/login.php"
UPLOAD_URL = f"{ADMIN_URL}/upload.php"
EDIT_URL = f"{ADMIN_URL}/edit.php"
INDEX_URL = f"{ADMIN_URL}/index.php"

session = requests.Session()

def login():
    session.post(LOGIN_URL, data={'username': 'admin', 'password': 'password123'})

def upload_file(filename, content, mime_type):
    print(f"Uploading {filename}...")
    files = {'file': (filename, content, mime_type)}
    # Checkbox logic: send key='1' for True, omit for False.
    # We want lock_on_access=False.
    data = {'max_minutes': '0', 'allow_download': '1'}
    resp = session.post(UPLOAD_URL, files=files, data=data)
    
    # Find the row with the filename, then get the edit link in that row/context
    # Simple regex approach: Look for filename, then look for id close to it?
    # Or just Find all ids, and since we just uploaded, it should be the last one?
    # Safer: filename specific regex.
    # HTML structure: <tr><td>filename</td><td>...id...</td>...
    
    # We'll just grab the LAST edit link, assuming new files are appended.
    matches = re.findall(r'href="edit\.php\?id=([a-f0-9]+)"', session.get(INDEX_URL).text)
    if not matches:
        print("Failed to get ID")
        exit(1)
    file_id = matches[-1] # GET LAST ONE
    print(f"Uploaded ID: {file_id}")
    return file_id

def set_download(file_id, allow):
    print(f"Setting allow_download={allow} for {file_id}...")
    # edit.php uses isset, so we must omit keys for False.
    # We also need to send other keys if we want to preserve them? 
    # edit.php implementation:
    # $updates = [ ..., 'lock_on_access' => isset(), ... ]
    # If we omit lock_on_access, it gets set to False.
    # Verification requirement: Ensure lock_on_access stays False. So we OMIT it.
    
    data = {
        'max_minutes': '0',
    }
    if allow:
        data['allow_download'] = '1'
        
    session.post(f"{EDIT_URL}?id={file_id}", data=data)

def check_html(file_id, check_str, exists=True):
    url = f"{BASE_URL}/index.php?id={file_id}"
    resp = requests.get(url)
    if (check_str in resp.text) == exists:
        print(f"PASS: '{check_str}' {'found' if exists else 'not found'} as expected.")
    else:
        print(f"FAIL: '{check_str}' {'NOT found' if exists else 'found'}!")
        exit(1)

def check_stream(file_id, expect_code):
    url = f"{BASE_URL}/index.php?id={file_id}&action=stream"
    resp = requests.get(url)
    if resp.status_code == expect_code:
        print(f"PASS: Stream returned {expect_code}")
    else:
        print(f"FAIL: Stream returned {resp.status_code}, expected {expect_code}")
        exit(1)

def run():
    login()
    
    # 1. Text File (Renderable)
    txt_id = upload_file('test.txt', b'Hello World', 'text/plain')
    
    # Disable Download
    set_download(txt_id, False)
    # Check HTML for context menu block
    check_html(txt_id, "document.addEventListener('contextmenu'", True)
    # Check Stream (Should be 200 because it's text/renderable)
    check_stream(txt_id, 200)
    
    # 2. Binary File (Non-Renderable)
    bin_id = upload_file('test.bin', b'\x00\x01\x02', 'application/octet-stream')
    
    # Default is ON (from upload)
    check_stream(bin_id, 200)
    
    # Disable Download
    set_download(bin_id, False)
    # Check Stream (Should be 403 because it's non-renderable and download is off)
    check_stream(bin_id, 403)
    
    # Re-enable
    set_download(bin_id, True)
    check_stream(bin_id, 200)

    print("\nPHASE 2 VERIFIED")

if __name__ == "__main__":
    run()
