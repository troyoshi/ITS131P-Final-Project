const inventoryData = [
    {
      id: 1,
      name: "Rice Sacks",
      unit: "sacks",
      category: "Food",
      stock: 342,
      threshold: 120,
      lastRestock: "Jun 14, 2026",
      restocks: [
        { qty: 200, date: "Jun 14, 2026 - 8:30 AM", source: "Main Warehouse Delivery" },
        { qty: 80, date: "Jun 10, 2026 - 2:15 PM", source: "LGU Supply Transfer" }
      ],
      outgoing: [
        { qty: 55, date: "Jun 15, 2026 - 9:20 AM", location: "Brgy. Masaya", note: "Family pack distribution" },
        { qty: 89, date: "Jun 14, 2026 - 1:00 PM", location: "Pag-asa Center", note: "Relief distribution for families" },
        { qty: 40, date: "Jun 13, 2026 - 3:40 PM", location: "Brgy. Maliwanag", note: "Emergency allocation" }
      ]
    },
    {
      id: 2,
      name: "Canned Goods",
      unit: "cans",
      category: "Food",
      stock: 890,
      threshold: 250,
      lastRestock: "Jun 12, 2026",
      restocks: [
        { qty: 300, date: "Jun 12, 2026 - 10:00 AM", source: "NGO Donation" },
        { qty: 150, date: "Jun 08, 2026 - 11:45 AM", source: "Community Drive" }
      ],
      outgoing: [
        { qty: 120, date: "Jun 15, 2026 - 11:00 AM", location: "Brgy. Masaya", note: "Meal support packs" },
        { qty: 75, date: "Jun 14, 2026 - 4:10 PM", location: "Brgy. Maligaya", note: "Senior support allocation" }
      ]
    },
    {
      id: 3,
      name: "Drinking Water",
      unit: "liters",
      category: "Water",
      stock: 60,
      threshold: 150,
      lastRestock: "Jun 09, 2026",
      restocks: [
        { qty: 200, date: "Jun 09, 2026 - 9:00 AM", source: "Water Tank Refill" }
      ],
      outgoing: [
        { qty: 110, date: "Jun 15, 2026 - 8:00 AM", location: "Brgy. Maliwanag", note: "Daily water ration" },
        { qty: 90, date: "Jun 14, 2026 - 8:30 AM", location: "Brgy. Masaya", note: "Center supply refill" }
      ]
    },
    {
      id: 4,
      name: "Hygiene Kits",
      unit: "kits",
      category: "Hygiene",
      stock: 180,
      threshold: 70,
      lastRestock: "Jun 11, 2026",
      restocks: [
        { qty: 100, date: "Jun 11, 2026 - 3:00 PM", source: "Health Office Supply" }
      ],
      outgoing: [
        { qty: 25, date: "Jun 15, 2026 - 1:15 PM", location: "Brgy. Masaya", note: "New arrivals assistance" },
        { qty: 18, date: "Jun 13, 2026 - 10:20 AM", location: "Brgy. Pag-asa", note: "Family hygiene support" }
      ]
    },
    {
      id: 5,
      name: "Medicines",
      unit: "boxes",
      category: "Medicine",
      stock: 40,
      threshold: 60,
      lastRestock: "Jun 07, 2026",
      restocks: [
        { qty: 70, date: "Jun 07, 2026 - 4:40 PM", source: "Municipal Health Unit" }
      ],
      outgoing: [
        { qty: 12, date: "Jun 15, 2026 - 9:45 AM", location: "Brgy. Pag-asa", note: "Fever and first-aid support" },
        { qty: 10, date: "Jun 14, 2026 - 2:30 PM", location: "Brgy. Maligaya", note: "Clinic request" }
      ]
    },
    {
      id: 6,
      name: "Blankets",
      unit: "pieces",
      category: "Shelter",
      stock: 95,
      threshold: 40,
      lastRestock: "Jun 13, 2026",
      restocks: [
        { qty: 50, date: "Jun 13, 2026 - 12:20 PM", source: "Shelter Support Donation" }
      ],
      outgoing: [
        { qty: 15, date: "Jun 15, 2026 - 6:00 PM", location: "Brgy. Maligaya", note: "Night shelter support" },
        { qty: 20, date: "Jun 14, 2026 - 7:25 PM", location: "Brgy. Maliwanag", note: "Emergency bedding issue" }
      ]
    }
  ];

  const tableBody = document.getElementById("inventoryTableBody");
  const detailsPanel = document.getElementById("detailsPanel");
  const searchInput = document.getElementById("searchInput");
  const itemCount = document.getElementById("itemCount");
  const categoryBadge = document.getElementById("selectedCategoryBadge");
  const filterChips = document.querySelectorAll(".filter-chip");

  let selectedCategory = "All";
  let selectedItemId = null;

  function getStatusBadge(item) {
    if (item.stock <= item.threshold) {
      return '<span class="badge badge-orange">Low Stock</span>';
    }
    if (item.stock <= item.threshold * 1.5) {
      return '<span class="badge badge-teal">Monitor</span>';
    }
    return '<span class="badge badge-green">Stable</span>';
  }

  function renderTable() {
    const term = searchInput.value.toLowerCase().trim();

    const filtered = inventoryData.filter(item => {
      const matchesSearch =
        item.name.toLowerCase().includes(term) ||
        item.category.toLowerCase().includes(term) ||
        item.unit.toLowerCase().includes(term);

      const matchesCategory =
        selectedCategory === "All" || item.category === selectedCategory;

      return matchesSearch && matchesCategory;
    });

    itemCount.textContent = `${filtered.length} item${filtered.length !== 1 ? "s" : ""}`;

    if (!filtered.length) {
      tableBody.innerHTML = `
        <tr>
          <td colspan="4" style="padding:18px 12px;color:var(--gray-500);">
            No inventory items found.
          </td>
        </tr>
      `;
      return;
    }

    tableBody.innerHTML = filtered.map(item => `
      <tr class="inventory-row ${selectedItemId === item.id ? "active" : ""}" onclick="selectItem(${item.id})">
        <td>
          <div class="item-name">${item.name}</div>
          <div class="item-sub">Last restock: ${item.lastRestock}</div>
        </td>
        <td>${item.category}</td>
        <td><span class="stock-num">${item.stock}</span> ${item.unit}</td>
        <td>${getStatusBadge(item)}</td>
      </tr>
    `).join("");
  }

  function selectItem(id) {
    selectedItemId = id;
    const item = inventoryData.find(entry => entry.id === id);

    categoryBadge.textContent = item.category;

    detailsPanel.innerHTML = `
      <div class="detail-head">
        <h3>${item.name}</h3>
        <div class="detail-meta">
          <span class="badge badge-gray">${item.category}</span>
          ${getStatusBadge(item)}
        </div>
      </div>

      <div class="stock-summary">
        <div class="mini-stat">
          <div class="mini-stat-label">Current Stock</div>
          <div class="mini-stat-value">${item.stock}</div>
        </div>
        <div class="mini-stat">
          <div class="mini-stat-label">Unit</div>
          <div class="mini-stat-value">${item.unit}</div>
        </div>
        <div class="mini-stat">
          <div class="mini-stat-label">Low Stock Alert</div>
          <div class="mini-stat-value">${item.threshold}</div>
        </div>
      </div>

      <div class="form-block">
        <h5>Add Stocks</h5>
        <div class="form-row">
          <div class="field">
            <label for="addStockQty">Quantity</label>
            <input type="number" id="addStockQty" min="1" placeholder="Enter quantity">
          </div>

          <div class="field">
            <label for="addStockSource">Source</label>
            <input type="text" id="addStockSource" placeholder="e.g. Warehouse delivery">
          </div>

          <button class="btn btn-primary" onclick="addStock(${item.id})">Add Stock</button>
        </div>
        <div class="muted-note">Adding stock updates the current balance and records the latest restock entry.</div>
      </div>

      <div class="record-section">
        <h5>Restock History</h5>
        <div class="record-list">
          ${item.restocks.map(entry => `
            <div class="record-item">
              <div class="record-top">
                <span>+${entry.qty} ${item.unit}</span>
                <span>${entry.date}</span>
              </div>
              <div class="record-sub">Source: ${entry.source}</div>
            </div>
          `).join("")}
        </div>
      </div>

      <div class="record-section">
        <h5>Outgoing / Distribution Records</h5>
        <div class="record-list">
          ${item.outgoing.map(entry => `
            <div class="record-item">
              <div class="record-top">
                <span>- ${entry.qty} ${item.unit}</span>
                <span>${entry.date}</span>
              </div>
              <div class="record-sub">Given to: ${entry.location}</div>
              <div class="record-sub">${entry.note}</div>
            </div>
          `).join("")}
        </div>
      </div>
    `;

    renderTable();
  }

  function addStock(id) {
    const item = inventoryData.find(entry => entry.id === id);
    const qtyInput = document.getElementById("addStockQty");
    const sourceInput = document.getElementById("addStockSource");

    const qty = parseInt(qtyInput.value, 10);
    const source = sourceInput.value.trim();

    if (!qty || qty < 1) {
      alert("Please enter a valid stock quantity.");
      return;
    }

    if (!source) {
      alert("Please enter the stock source.");
      return;
    }

    item.stock += qty;
    item.lastRestock = "Just now";
    item.restocks.unshift({
      qty: qty,
      date: "Just now",
      source: source
    });

    selectItem(id);
  }

  filterChips.forEach(chip => {
    chip.addEventListener("click", function () {
      filterChips.forEach(btn => btn.classList.remove("active"));
      this.classList.add("active");
      selectedCategory = this.dataset.category;
      renderTable();
    });
  });

  searchInput.addEventListener("input", renderTable);

  renderTable();