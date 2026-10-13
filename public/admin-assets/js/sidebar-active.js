/**
 * Sidebar Active State Handler
 *
 * Icon/minimized mode is intentionally state-driven:
 * - isSubmenuOpen: whether any flyout submenu is open.
 * - activeSubmenuId: the id of the open submenu, or null.
 * - hoveredLabelId: the direct menu link currently allowed to show its label.
 */

document.addEventListener("DOMContentLoaded", function () {
    const htmlElement = document.documentElement;
    const sidebar = document.getElementById("sidebar");

    let isSubmenuOpen = false;
    let activeSubmenuId = null;
    let hoveredLabelId = null;
    let suppressNextClick = false;

    function isIconSidebar() {
        return htmlElement.getAttribute("data-sidebar") === "icon";
    }

    const MENU_LINK_SELECTOR = ".pe-main-menu > .pe-slide > .pe-nav-link, .pe-main-menu > .pe-slide form > .pe-nav-link";

    function directMenuLinks() {
        return sidebar
            ? sidebar.querySelectorAll(MENU_LINK_SELECTOR)
            : [];
    }

    function directSubmenus() {
        return sidebar
            ? sidebar.querySelectorAll(".pe-main-menu > .pe-slide > .pe-slide-menu")
            : [];
    }

    function getMenuId(link) {
        if (!link) return null;

        if (!link.dataset.peIconMenuId) {
            const collapseId = getCollapseId(link);
            link.dataset.peIconMenuId =
                collapseId || "pe-icon-link-" + Math.random().toString(36).slice(2);
        }

        return link.dataset.peIconMenuId;
    }

    function getCollapseId(trigger) {
        const href = trigger?.getAttribute("href") || "";
        return href.startsWith("#") ? href.slice(1) : null;
    }

    function setTriggerState(trigger, isOpen) {
        if (!trigger) return;

        trigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
        trigger.closest(".pe-slide")?.classList.toggle("open", isOpen);
        trigger
            .querySelector(".pe-nav-arrow")
            ?.classList.toggle("open", isOpen);
    }

    function clearHoveredLabel() {
        hoveredLabelId = null;
        directMenuLinks().forEach(function (link) {
            link.classList.remove("pe-icon-label-visible");
        });
    }

    function showHoveredLabel(link) {
        clearHoveredLabel();

        if (!isIconSidebar() || isSubmenuOpen || !link) return;

        hoveredLabelId = getMenuId(link);
        link.classList.add("pe-icon-label-visible");
    }

    function closeCollapseNow(collapseEl) {
        collapseEl.classList.remove(
            "show",
            "collapsing",
            "pe-icon-flyout-open",
        );
        collapseEl.classList.add("collapse", "pe-icon-flyout-closed");
        collapseEl.setAttribute("aria-hidden", "true");
        collapseEl.style.removeProperty("height");
        collapseEl.style.removeProperty("display");
    }

    function openCollapseNow(collapseEl, trigger) {
        collapseEl.classList.remove("collapsing", "pe-icon-flyout-closed");
        collapseEl.classList.add("collapse", "show", "pe-icon-flyout-open");
        collapseEl.removeAttribute("aria-hidden");
        collapseEl.style.removeProperty("height");
        collapseEl.style.removeProperty("display");

        isSubmenuOpen = true;
        activeSubmenuId = collapseEl.id;
        clearHoveredLabel();
        setTriggerState(trigger, true);
        syncSidebarStateClass();
    }

    function syncSidebarStateClass() {
        if (!sidebar) return;
        const hasOpenIconSubmenu = isIconSidebar() && isSubmenuOpen;

        sidebar.classList.toggle("pe-icon-submenu-open", hasOpenIconSubmenu);
        htmlElement.classList.toggle("pe-icon-submenu-open", hasOpenIconSubmenu);
        document.body?.classList.toggle(
            "pe-icon-submenu-open",
            hasOpenIconSubmenu,
        );
    }

    function closeAllSubmenus() {
        directSubmenus().forEach(function (submenu) {
            closeCollapseNow(submenu);
        });

        if (sidebar) {
            sidebar
                .querySelectorAll('[data-bs-toggle="collapse"][aria-expanded="true"]')
                .forEach(function (trigger) {
                    setTriggerState(trigger, false);
                });
        }

        isSubmenuOpen = false;
        activeSubmenuId = null;
        syncSidebarStateClass();
    }

    function openSubmenu(trigger) {
        const targetId = getCollapseId(trigger);
        const targetCollapse = targetId ? document.getElementById(targetId) : null;

        if (!targetCollapse) return;

        closeAllSubmenus();
        openCollapseNow(targetCollapse, trigger);
    }

    function isInsideOpenSubmenu(target) {
        if (!sidebar || !activeSubmenuId) return false;

        const activeSubmenu = document.getElementById(activeSubmenuId);
        return !!activeSubmenu && activeSubmenu.contains(target);
    }

    function initializeIconMode() {
        clearHoveredLabel();
        closeAllSubmenus();

        directMenuLinks().forEach(function (link) {
            getMenuId(link);
        });
    }

    if (isIconSidebar()) {
        initializeIconMode();
    }

    if (sidebar) {
        sidebar.addEventListener("mouseover", function (event) {
            const link = event.target.closest(MENU_LINK_SELECTOR);

            if (!link || !sidebar.contains(link)) return;
            showHoveredLabel(link);
        });

        sidebar.addEventListener("mouseout", function (event) {
            const link = event.target.closest(MENU_LINK_SELECTOR);

            if (!link || !sidebar.contains(link)) return;
            if (link.contains(event.relatedTarget)) return;
            if (hoveredLabelId === getMenuId(link)) clearHoveredLabel();
        });
    }

    function dismissOpenSubmenuEvent(event) {
        if (!isIconSidebar() || !sidebar || !isSubmenuOpen) return false;
        if (isInsideOpenSubmenu(event.target)) return false;

        const clickedDirectLink = sidebar.contains(event.target)
            ? event.target.closest(MENU_LINK_SELECTOR)
            : null;

        closeAllSubmenus();

        if (clickedDirectLink) {
            showHoveredLabel(clickedDirectLink);
        }

        suppressNextClick = true;
        window.setTimeout(function () {
            suppressNextClick = false;
        }, 700);

        event.preventDefault();
        event.stopImmediatePropagation();
        event.stopPropagation();

        return true;
    }

    ["pointerdown", "mousedown", "touchstart"].forEach(function (eventName) {
        window.addEventListener(
            eventName,
            function (event) {
                dismissOpenSubmenuEvent(event);
            },
            true,
        );
    });

    window.addEventListener(
        "click",
        function (event) {
            if (!isIconSidebar() || !sidebar) return;
            if (isInsideOpenSubmenu(event.target)) return;
            if (!suppressNextClick && !isSubmenuOpen) return;

            suppressNextClick = false;

            if (isSubmenuOpen) {
                closeAllSubmenus();
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            event.stopPropagation();
        },
        true,
    );

    document.addEventListener(
        "click",
        function (event) {
            if (!isIconSidebar() || !sidebar) return;

            if (suppressNextClick) {
                suppressNextClick = false;
                event.preventDefault();
                event.stopImmediatePropagation();
                event.stopPropagation();
                return;
            }

            const clickedInsideSidebar = sidebar.contains(event.target);
            const clickedInsideOpenSubmenu = isInsideOpenSubmenu(event.target);

            if (isSubmenuOpen && !clickedInsideOpenSubmenu) {
                closeAllSubmenus();

                event.preventDefault();
                event.stopImmediatePropagation();
                event.stopPropagation();
                return;
            }

            if (clickedInsideOpenSubmenu) return;
            if (!clickedInsideSidebar) return;

            const trigger = event.target.closest('[data-bs-toggle="collapse"]');
            if (!trigger || !sidebar.contains(trigger)) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            event.stopPropagation();

            openSubmenu(trigger);
        },
        true,
    );

    window.addEventListener("pageshow", function () {
        if (isIconSidebar()) initializeIconMode();
    });

    window.addEventListener("beforeunload", function () {
        if (isIconSidebar()) closeAllSubmenus();
    });

    if (isIconSidebar()) return;

    document
        .querySelectorAll("#sidebar .pe-nav-link.active")
        .forEach(function (activeLink) {
            const collapseParent = activeLink.closest(".pe-slide-menu.collapse");
            if (!collapseParent) return;

            collapseParent.classList.add("show");

            const parentTrigger = document.querySelector(
                '[data-bs-toggle="collapse"][href="#' + collapseParent.id + '"]',
            );

            if (parentTrigger) {
                parentTrigger.setAttribute("aria-expanded", "true");
                parentTrigger.closest(".pe-slide")?.classList.add("open");
            }
        });
});
