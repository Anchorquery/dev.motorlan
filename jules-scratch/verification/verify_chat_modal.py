from playwright.sync_api import sync_playwright
import socket
import time

def is_port_in_use(port: int) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        return s.connect_ex(('localhost', port)) == 0

def run(playwright):
    port = 5173
    for _ in range(20): # 20 attempts, 1 second apart
        if is_port_in_use(port):
            break
        time.sleep(1)
    else:
        raise ConnectionError(f"Port {port} not in use after multiple attempts")


    browser = playwright.chromium.launch()
    page = browser.new_page()
    page.goto(f"http://localhost:{port}/store/producto-de-ejemplo")
    page.get_by_role("button", name="Chat").click()
    page.screenshot(path="jules-scratch/verification/chat_modal.png")
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
