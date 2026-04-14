from playwright.sync_api import Page, expect, sync_playwright
import re

def verify_site(page: Page):
    print("Testing Home.html...")
    page.goto("http://localhost:8000/Home.html")
    page.wait_for_load_state("networkidle")
    canvas_container = page.locator("#three-container")
    expect(canvas_container).to_be_visible() 
    expect(page.locator("html")).to_have_class(re.compile(r"lenis"))
    page.screenshot(path="/home/jules/verification/home.png")
    
    print("Testing Eingabe.php...")
    page.goto("http://localhost:8000/Eingabe.php")
    page.wait_for_load_state("networkidle")
    expect(canvas_container).to_be_visible()
    expect(page.locator("html")).to_have_class(re.compile(r"lenis"))
    page.screenshot(path="/home/jules/verification/eingabe.png")
    
    print("Testing Bearbeitung.php...")
    page.goto("http://localhost:8000/Bearbeitung.php")
    page.wait_for_load_state("networkidle")
    expect(canvas_container).to_be_visible()
    expect(page.locator("html")).to_have_class(re.compile(r"lenis"))
    page.screenshot(path="/home/jules/verification/bearbeitung.png")

    print("Verification successful!")

if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            verify_site(page)
        except Exception as e:
            print(f"Verification failed: {e}")
        finally:
            browser.close()
