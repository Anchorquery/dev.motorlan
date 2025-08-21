from playwright.sync_api import sync_playwright, expect

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto("http://192.168.0.2:5173/")

        # Expect a title "to contain" a substring.
        expect(page).to_have_title("Motorlan")

        # Listen for console events and print them
        page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))

        # Take a screenshot to see what the page looks like
        page.screenshot(path="jules-scratch/verification/login_page.png")

        # Log in
        page.get_by_label("Email").fill("admin@motorlan.com")
        page.get_by_label("Password").fill("admin")
        page.get_by_role("button", name="Login").click()

        # Wait for navigation to complete
        page.wait_for_url("http://192.168.0.2:5173/dashboards/motors")

        # Check if "My Purchases" is visible
        my_purchases_menu = page.get_by_role("listitem").filter(has_text="My Purchases")
        expect(my_purchases_menu).to_be_visible()

        # Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")

        browser.close()

run()
