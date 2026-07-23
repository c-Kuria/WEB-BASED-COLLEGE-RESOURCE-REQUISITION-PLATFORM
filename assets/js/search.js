const searchInput = document.getElementById("searchInput");

if (searchInput) {
  searchInput.addEventListener("keyup", function () {
    const value = this.value.toLowerCase();

    const rows = document.querySelectorAll("#dataTable tr");

    rows.forEach((row, index) => {
      if (index === 0) return;

      const text = row.innerText.toLowerCase();

      row.style.display = text.includes(value) ? "" : "none";
    });
  });
}
