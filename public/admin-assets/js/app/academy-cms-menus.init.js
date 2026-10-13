document.addEventListener("DOMContentLoaded", function () {
    let menusChoice = document.getElementById("menus-choice");
    if (menusChoice) {
        const menusChoice = new Choices("#menus-choice", {
            placeholderValue: "Select Menu",
            searchPlaceholderValue: "Search...",
            removeItemButton: true,
            itemSelectText: "Press to select",
        });
    }
});
