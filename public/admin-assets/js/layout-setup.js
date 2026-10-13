// layout setup

// Get HTML root element
const htmlElement = document.documentElement;

// List of all settings to check
const settings = [
    { attribute: "data-layout", defaultValue: "vertical" },
    { attribute: "data-bs-theme", defaultValue: "light" },
    { attribute: "data-content-width", defaultValue: "default" },
    { attribute: "dir", defaultValue: "ltr" },
    { attribute: "data-sidebar-size", defaultValue: "default" },
    { attribute: "data-sidebar-color", defaultValue: "default" },
    { attribute: "data-sidebar", defaultValue: "light" },
    { attribute: "data-theme-colors", defaultValue: "default" },
];

// Apply each setting from localStorage or use default
settings.forEach((setting) => {
    let valueToApply;
    if (setting.attribute === "data-theme-colors") {
        valueToApply = htmlElement.getAttribute("data-theme-colors") || localStorage.getItem(setting.attribute) || setting.defaultValue;
    } else {
        const savedValue = localStorage.getItem(setting.attribute);
        valueToApply = savedValue || setting.defaultValue;
    }

    // Apply to HTML element
    htmlElement.setAttribute(setting.attribute, valueToApply);
    if (setting.attribute === "dir") updateLayoutDir(valueToApply);
    else if (setting.attribute === "data-bs-theme")
        setTheme(valueToApply);

    const radioSelector = `input[name="${setting.attribute}"][value="${valueToApply}"]`;
    const radioElement = document.querySelector(radioSelector);
    if (radioElement) {
        radioElement.checked = true;
    }
});

updateSimpleBar(htmlElement.getAttribute("data-layout"));

if (document.documentElement.getAttribute("data-layout") === "horizontal") {
    removeHorizontalAttributes()
}

function removeHorizontalAttributes() {
    document.documentElement.removeAttribute("data-sidebar");
    document.documentElement.removeAttribute("data-sidebar-size");
    document.documentElement.removeAttribute("data-sidebar-color");
}

function setTheme(valueToApply) {
    // Update corresponding radio button in the modal
    let THEME_MODE = "";
    if (valueToApply === "auto") {
        THEME_MODE = window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
        htmlElement.setAttribute("data-bs-theme", THEME_MODE);
    } else {
        THEME_MODE = valueToApply;
    }
}

// Function to set an attribute and save to localStorage
function setAndSaveAttribute(attributeName, value) {
    htmlElement.setAttribute(attributeName, value);
    localStorage.setItem(attributeName, value);
}

function updateLayoutDir(value) {
    const bootstrapCss = document.getElementById("bootstrap-style");
    const appCss = document.getElementById("app-style");
    if (bootstrapCss === null || appCss === null) return;
    if (value === "rtl") {
        bootstrapCss.href = bootstrapCss.href.replace(
            "bootstrap.min.css",
            "bootstrap-rtl.min.css"
        );
        appCss.href = appCss.href.replace("app.min.css", "app-rtl.min.css");
    } else {
        bootstrapCss.href = bootstrapCss.href.replace(
            "bootstrap-rtl.min.css",
            "bootstrap.min.css"
        );
        appCss.href = appCss.href.replace("app-rtl.min.css", "app.min.css");
    }
}

function updateSimpleBar(value) {
    setTimeout(() => {
        const sidebarSimpleBarMenu = document.getElementById('sidebar-simplebar');
        if ((value === "vertical" || value === "horizontal" || value === "semibox") && document.documentElement.getAttribute("data-sidebar") !== "icon") {
            if (sidebarSimpleBarMenu) {
                const allMenus = sidebarSimpleBarMenu.querySelector('ul.pe-main-menu');
                if (allMenus) {
                    sidebarSimpleBarMenu.innerHTML = allMenus.parentElement.innerHTML;
                }
                // Add 'simplebar' class to initialize it
                sidebarSimpleBarMenu.setAttribute('data-simplebar', '');

                // Initialize SimpleBar for custom scrolling
                if (window.SimpleBar) {
                    new SimpleBar(sidebarSimpleBarMenu);  // Initialize a new SimpleBar instance
                }
            }
        } else {
            if (window.SimpleBar) {
                const simpleBarInstance = SimpleBar.instances.get(sidebarSimpleBarMenu);
                if (simpleBarInstance) {
                    simpleBarInstance.unMount();  // Unmount and remove the instance
                    const allMenus = sidebarSimpleBarMenu.querySelector('ul.pe-main-menu');
                    if (allMenus) {
                        sidebarSimpleBarMenu.innerHTML = allMenus.parentElement.innerHTML;
                    }
                }
            }
        }
    }, 1000);
}
// Function to handle radio button selection
function handleRadioChange(event) {
    const { name, value } = event.target;

    // Set attribute based on option group
    switch (name) {
        case "data-bs-theme":
            setAndSaveAttribute("data-bs-theme", value);
            setTheme(value);
            break;
        case "data-layout":
            setAndSaveAttribute("data-layout", value);
            if(value === "horizontal")
                removeHorizontalAttributes();
            updateSimpleBar(value);
            break;
        case "data-content-width":
            setAndSaveAttribute("data-content-width", value);
            break;
        case "dir":
            setAndSaveAttribute("dir", value);
            updateLayoutDir(value);
            break;
        case "data-sidebar-size":
            setAndSaveAttribute("data-sidebar-size", value);
            updateSimpleBar(document.documentElement.getAttribute("data-layout"));
            break;
        case "data-sidebar":
            if (document.documentElement.getAttribute("data-layout") !== "horizontal")
                setAndSaveAttribute("data-sidebar", value);
            break;
        case "data-sidebar-color":
            setAndSaveAttribute("data-sidebar-color", value);
            break;
        case "data-theme-colors":
            setAndSaveAttribute("data-theme-colors", value);
            break;
        default:
            break;
    }
}

// Attach event listeners to all radio buttons in the customizer
document.querySelectorAll('.layout-customizer input[type="radio"]').forEach((radio) => {
    radio.addEventListener("change", handleRadioChange);
});

// dark light mode toggle
const toggleMode = document.getElementById("toggleMode");
const lightModeButton = document.getElementById("lightModeBtn");
const darkModeButton = document.getElementById("darkModeBtn");

const themeTransition = {
    tileSize: 64,
    tileDuration: 520,
    maxStagger: 420,
};

function syncThemeToggleButtons(theme) {
    if (theme === "dark") {
        lightModeButton?.classList.remove("active");
        darkModeButton?.classList.add("active");
    } else {
        darkModeButton?.classList.remove("active");
        lightModeButton?.classList.add("active");
    }
}

function updateThemeMode(newTheme) {
    setAndSaveAttribute("data-bs-theme", newTheme);
    setTheme(newTheme);
    syncThemeToggleButtons(newTheme);
}

function getCurrentPageBackground() {
    const bodyBackground = window.getComputedStyle(document.body).backgroundColor;
    const htmlBackground = window.getComputedStyle(htmlElement).backgroundColor;

    return bodyBackground && bodyBackground !== "rgba(0, 0, 0, 0)"
        ? bodyBackground
        : htmlBackground || "#ffffff";
}

function runTileThemeTransition(event, updateTheme) {
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion) {
        updateTheme();
        return;
    }

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const columns = Math.ceil(viewportWidth / themeTransition.tileSize);
    const rows = Math.ceil(viewportHeight / themeTransition.tileSize);
    const originX = event?.clientX ?? viewportWidth / 2;
    const originY = event?.clientY ?? 0;
    const originColumn = Math.floor(originX / themeTransition.tileSize);
    const originRow = Math.floor(originY / themeTransition.tileSize);
    const maxDistance = Math.hypot(
        Math.max(originColumn, columns - originColumn),
        Math.max(originRow, rows - originRow)
    ) || 1;
    const overlay = document.createElement("div");
    const oldBackground = getCurrentPageBackground();

    overlay.className = "theme-tile-transition";
    overlay.style.setProperty("--theme-tile-columns", columns);
    overlay.style.setProperty("--theme-tile-size", `${themeTransition.tileSize}px`);
    overlay.setAttribute("aria-hidden", "true");

    for (let row = 0; row < rows; row += 1) {
        for (let column = 0; column < columns; column += 1) {
            const tile = document.createElement("span");
            const distance = Math.hypot(column - originColumn, row - originRow);
            const delay = (distance / maxDistance) * themeTransition.maxStagger;

            tile.style.backgroundColor = oldBackground;
            tile.style.transitionDelay = `${delay}ms`;
            overlay.appendChild(tile);
        }
    }

    document.body.appendChild(overlay);

    const finishTransition = () => {
        requestAnimationFrame(() => {
            overlay.classList.add("is-revealing");
            window.setTimeout(() => {
                overlay.remove();
            }, themeTransition.tileDuration + themeTransition.maxStagger + 80);
        });
    };

    if (document.startViewTransition) {
        const viewTransition = document.startViewTransition(updateTheme);
        viewTransition.ready.then(finishTransition).catch(finishTransition);
    } else {
        updateTheme();
        finishTransition();
    }
}

toggleMode?.addEventListener("click", (event) => {
    const currentTheme = htmlElement.getAttribute("data-bs-theme");
    const newTheme = currentTheme === "light" ? "dark" : "light";

    runTileThemeTransition(event, () => updateThemeMode(newTheme));
});

// vertical toggle button
const toggleButton = document.getElementById("toggleSidebar");
const sideBarBackdrop = document.getElementById("sidebar-backdrop");
const sidebarEl = document.getElementById("sidebar");

sideBarBackdrop?.addEventListener("click", () => {
    sidebarEl?.classList.remove("show");
    sideBarBackdrop?.classList.remove("show");
});

// Close mobile sidebar on nav link click
sidebarEl?.querySelectorAll(".pe-nav-link").forEach((link) => {
    link.addEventListener("click", () => {
        if (window.innerWidth < 992) {
            sidebarEl?.classList.remove("show");
            sideBarBackdrop?.classList.remove("show");
        }
    });
});

function removeShowClassFromSidebar() {
    const sidebarSimpleBarMenu = document.getElementById('sidebar-simplebar');
    sidebarSimpleBarMenu?.querySelectorAll('.pe-slide-menu.collapse.show').forEach((element) => {
        element.classList.remove('show');
        element.classList.remove('pe-icon-flyout-open');
        element.classList.add('pe-icon-flyout-closed');
        element.setAttribute('aria-hidden', 'true');
    });
    sidebarSimpleBarMenu?.querySelectorAll('[data-bs-toggle="collapse"][aria-expanded="true"]').forEach((trigger) => {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.closest('.pe-slide')?.classList.remove('open');
        trigger.querySelector('.pe-nav-arrow')?.classList.remove('open');
    });
    document.getElementById('sidebar')?.classList.remove('pe-icon-submenu-open');
}

function updateSidebarToggleIcon() {
    const icon = toggleButton?.querySelector("i");
    if (!icon) return;

    const isMinimized = htmlElement.getAttribute("data-sidebar") === "icon";
    icon.classList.toggle("bi-arrow-bar-left", !isMinimized);
    icon.classList.toggle("bi-arrow-bar-right", isMinimized);
    toggleButton.setAttribute(
        "title",
        isMinimized ? "Maximize sidebar" : "Minimize sidebar"
    );
}

toggleButton?.addEventListener("click", () => {
    const currentToggled = htmlElement.getAttribute("data-sidebar");
    if (window.innerWidth < 992) {
        const isShowing = sidebarEl?.classList.toggle("show");
        sideBarBackdrop?.classList.toggle("show", !!isShowing);
        removeHorizontalAttributes();
    } else {
        if (currentToggled === "icon") {
            setAndSaveAttribute("data-sidebar", "default");
        } else {
            setAndSaveAttribute("data-sidebar", "icon");
            removeShowClassFromSidebar();
        }
    }
    updateSidebarToggleIcon();
});

// horizontal toggle button
const horizontalToggle = document.getElementById("toggleHorizontal");
horizontalToggle?.addEventListener("click", () => {
    if (window.innerWidth < 1200) {
        // Toggle the data-vertical-layout value
        const horizontalAside = document.getElementById("horizontal-aside");
        horizontalAside.classList.toggle("show");
    }
});

// Function to handle sidebar menu active links
let currentUrl = window.location.pathname; // Get the current URL
const menuLinks = document.querySelectorAll("#sidebar .pe-main-menu .pe-nav-link"); // Select all menu links
if (currentUrl === "/")
    currentUrl = "/index.html";
const currentLayout = htmlElement.getAttribute("data-layout");
const currentSidebar = htmlElement.getAttribute("data-sidebar");

menuLinks.forEach((link) => {
    const linkHref = link.getAttribute("href"); // Get the href attribute of the link

    // Check if the current URL contains the link's href
    if (currentUrl.includes(linkHref)) {
        link.classList.add("active"); // Add active class to the link

        // Function to open all parent dropdowns
        const openParentDropdowns = (element) => {
            let parentDropdown = element.closest(".pe-has-sub");
            while (parentDropdown) {
                const collapseId = parentDropdown
                    .querySelector(".pe-nav-link")
                    .getAttribute("aria-controls");
                const collapseElement = document.getElementById(collapseId);
                if (collapseElement) {
                    parentDropdown.children[0].classList.add("active"); // Add active class to the parent link
                    if (currentLayout !== "horizontal" && currentSidebar !== "icon") {
                        collapseElement.classList.add("show"); // Open the dropdown
                        parentDropdown.children[0].setAttribute("aria-expanded", "true"); // Add active class to the parent link
                        parentDropdown
                            .querySelector(".pe-nav-arrow")
                            .classList.add("open"); // Add open class to the arrow
                    }
                }
                parentDropdown = parentDropdown.parentElement.closest(".pe-has-sub"); // Move to the next parent dropdown
            }
        };
        // Open all parent dropdowns for the active link
        openParentDropdowns(link);
    }
});

// active horizontal menu
const horizontalMenuLinks = document.querySelectorAll("#horizontal-menu .pe-nav-link");

horizontalMenuLinks.forEach((link) => {
    const linkHref = link.getAttribute("href"); // Get the href attribute of the link
    if (currentUrl.includes(linkHref)) {
        link.classList.add("active");
        const parentDropdown = link.closest(".pe-has-sub");
        if (parentDropdown) {
            const collapseId = parentDropdown
                .querySelector(".pe-nav-link")
                .getAttribute("aria-controls");
            const collapseElement = document.getElementById(collapseId);
            if (collapseElement) {
                parentDropdown.children[0].classList.add("active"); // Add active class to the parent link
                const parentParentDropdown = parentDropdown.parentElement.closest(".pe-has-sub");
                if (parentParentDropdown) {
                    const parentCollapseId = parentParentDropdown
                        .querySelector(".pe-nav-link")
                        .getAttribute("aria-controls");
                    const parentCollapseElement = document.getElementById(parentCollapseId);
                    if (parentCollapseElement) {
                        parentParentDropdown.children[0].classList.add("active"); // Add active class to the parent link
                    }
                }
            }
        }
    }
})

// rest layout to default
const resetBtn = document.getElementById("resetBtn"); // Get the resetBtn

resetBtn?.addEventListener("click", () => {
    localStorage.clear(); // Clear localStorage
    window.location.reload(); // Reload the page
});

// Run on page load
handleResponsiveSidebar();
updateSidebarToggleIcon();

function handleResponsiveSidebar() {
    if (window.innerWidth < 992) {
        document.documentElement.removeAttribute("data-sidebar");
        document.documentElement.removeAttribute("data-sidebar-size");
        document.documentElement.removeAttribute("data-sidebar-color");
    } else if (document.documentElement.getAttribute("data-layout") !== "horizontal") {
        const savedSidebar = localStorage.getItem("data-sidebar");
        const savedSidebarSize = localStorage.getItem("data-sidebar-size");
        const savedSidebarColor = localStorage.getItem("data-sidebar-color");

        if (savedSidebar) document.documentElement.setAttribute("data-sidebar", savedSidebar);
        if (savedSidebarSize) document.documentElement.setAttribute("data-sidebar-size", savedSidebarSize);
        if (savedSidebarColor) document.documentElement.setAttribute("data-sidebar-color", savedSidebarColor);

        if (document.documentElement.getAttribute("data-sidebar") === "icon") {
            removeShowClassFromSidebar();
        }
    }
    updateSidebarToggleIcon();
}

// Run on window resize
window.addEventListener("resize", handleResponsiveSidebar);
